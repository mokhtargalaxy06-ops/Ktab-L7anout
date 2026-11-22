<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Locality;
use App\Models\Region;
use App\Models\Role;
use App\Models\ShopDocument;
use App\Models\User;
use Database\Seeders\MoroccoGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopGeographyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
        $this->seed(MoroccoGeographySeeder::class);
    }

    public function test_official_geography_is_idempotent_and_children_are_scoped(): void
    {
        $this->seed(MoroccoGeographySeeder::class);
        $this->assertDatabaseCount('regions', 12);
        $this->assertDatabaseCount('cities', 1503);
        $this->assertDatabaseCount('localities', 205);
        $this->getJson('/api/v1/regions')->assertOk()->assertJsonCount(12, 'data');
        $this->getJson('/api/v1/cities?region_id=wrong')->assertUnprocessable();

        $tanger = City::where('name_fr', 'Tanger')->firstOrFail();
        $this->getJson('/api/v1/cities?region_id='.$tanger->region_id)->assertOk()->assertJsonFragment(['name_fr' => 'Tanger']);
        $this->getJson('/api/v1/localities?city_id='.$tanger->id)->assertOk()->assertJsonFragment(['name_ar' => 'مغوغة']);
        $this->assertSame(12, City::distinct()->count('region_id'));
    }

    public function test_owner_scope_and_location_relationships_are_enforced(): void
    {
        $owner = $this->account('shopkeeper');
        $other = $this->account('shopkeeper');
        $donor = $this->account('donor');
        $input = $this->profile();

        $this->postJson('/api/v1/shop/shops', $input)->assertUnauthorized();
        $this->asSession($donor)->postJson('/api/v1/shop/shops', $input)->assertForbidden();
        $this->asSession($owner)->postJson('/api/v1/shop/shops', [
            ...$input, 'region_id' => Region::where('id', '!=', $input['region_id'])->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors('city_id');
        $this->postJson('/api/v1/shop/shops', [
            ...$input, 'locality_id' => Locality::where('city_id', '!=', $input['city_id'])->value('id'),
        ])->assertUnprocessable()->assertJsonValidationErrors('locality_id');

        $shop = $this->postJson('/api/v1/shop/shops', [...$input, 'status' => 'verified', 'owner_id' => $other->id])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->assertDatabaseHas('shop_memberships', ['shop_id' => $shop, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->getJson('/api/v1/shop/shops')->assertOk()->assertJsonCount(1, 'data');
        $this->asSession($other)->getJson('/api/v1/shop/shops')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/shops/$shop")->assertForbidden();
        $this->patchJson("/api/v1/shop/shops/$shop", $input)->assertForbidden();
    }

    public function test_admin_controls_geography_and_inactive_parents_hide_descendants(): void
    {
        $input = ['code' => 'local-test', 'name_fr' => 'Fixture', 'name_ar' => 'اختبار', 'source_url' => 'https://example.test/source', 'source_date' => '2024-01-01', 'active' => true];
        $region = Region::where('code', '1')->firstOrFail();
        $data = [...$input, 'parent_id' => $region->id, 'reason' => 'Review fixture'];
        $url = '/api/v1/admin/geography/cities';
        $this->asSession($this->account('shopkeeper'))->postJson($url, $data)->assertForbidden();
        $admin = $this->account('administrator');
        $this->asSession($admin)->postJson($url, $data)->assertCreated()->assertJsonPath('data.region_id', $region->id);
        $coverage = $this->getJson('/api/v1/admin/regions/activity?coverage=uncovered&region_id='.$region->id)->assertOk();
        $this->assertGreaterThan(25, $coverage->json('data.cities.total'));
        parse_str(parse_url($coverage->json('data.cities.next_page_url'), PHP_URL_QUERY), $next);
        $this->assertSame($region->id, $next['region_id']);
        $this->assertSame('uncovered', $next['coverage']);
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->patchJson('/api/v1/admin/geography/regions/'.$region->id, [
            ...$region->only(['name_fr', 'name_ar', 'source_url', 'source_date']), 'active' => false, 'reason' => 'Coverage suspended',
        ])->assertOk();
        $this->getJson('/api/v1/cities?region_id='.$region->id)->assertNotFound();
        $this->getJson('/api/v1/admin/geography/cities?region_id='.$region->id)->assertOk();
        $this->getJson('/api/v1/admin/regions/activity?coverage=uncovered&region_id='.$region->id)->assertOk()
            ->assertJsonCount(0, 'data.cities.data');
        $this->getJson('/api/v1/admin/audit?target_id='.$region->id)->assertOk()
            ->assertJsonPath('data.0.actor_id', $admin->id)->assertJsonPath('data.0.action', 'geography.updated')
            ->assertJsonPath('data.0.changes.from.active', true)->assertJsonPath('data.0.changes.to.active', false)
            ->assertJsonPath('data.0.changes.reason', 'Coverage suspended');
    }

    public function test_evidence_review_and_debt_decisions_enforce_current_revision_and_owner(): void
    {
        Storage::fake('local');
        $owner = $this->account('shopkeeper');
        $admin = $this->account('administrator');
        $other = $this->account('shopkeeper');
        $input = $this->profile();
        $shop = $this->asSession($owner)->postJson('/api/v1/shop/shops', $input)->assertCreated()->json('data.id');
        $review = "/api/v1/admin/shops/$shop/review";
        $decision = ['decision' => 'verified', 'revision' => 1];
        $this->postJson($review, $decision)->assertForbidden();
        $evidence = $this->post("/api/v1/shop/shops/$shop/documents", [
            'document' => UploadedFile::fake()->createWithContent('review.pdf', "%PDF-1.4\n%%EOF\n"),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.documents.0.status', 'quarantined')->json('data.documents.0.id');
        $document = ShopDocument::findOrFail($evidence);
        Storage::disk('local')->assertExists($document->storage_key);
        $download = "/api/v1/shops/$shop/documents/$evidence";
        $this->asSession($admin)->get($download)->assertNotFound();
        $this->postJson($review, $decision)->assertUnprocessable();
        $document->forceFill(['status' => 'available'])->save(); // Scanner boundary.
        $this->get($download)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson($review, $decision)->assertOk()->assertJsonPath('data.status', 'verified');

        $request = $this->postJson("/api/v1/admin/shops/$shop/debt-requests", [
            'reference' => 'debt-1', 'amount_minor' => '25000',
        ])->assertCreated()->assertJsonPath('data.amount_minor', '25000')->json('data.id');
        $path = "/api/v1/shop/shops/$shop/debt-requests/$request/decision";
        $this->postJson($review, ['decision' => 'suspended', 'revision' => 1, 'reason' => 'Review required'])
            ->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->asSession($owner)->postJson($path, ['decision' => 'confirmed'])->assertForbidden();
        $this->asSession($admin)->postJson($review, $decision)->assertOk()->assertJsonPath('data.status', 'verified');
        $this->asSession($other)->getJson("/api/v1/shops/$shop/debt-requests")->assertForbidden();
        $this->postJson($path, ['decision' => 'confirmed'])->assertForbidden();
        $this->get($download)->assertForbidden();
        $this->asSession($owner)->getJson("/api/v1/shops/$shop/debt-requests")->assertOk()->assertJsonPath('data.0.reference', 'DEBT-1');
        $this->postJson($path, ['decision' => 'disputed'])->assertUnprocessable();
        $this->postJson($path, ['decision' => 'disputed', 'reason' => 'Amount differs'])->assertOk()->assertJsonPath('data.status', 'disputed');
        $this->postJson($path, ['decision' => 'confirmed'])->assertConflict();

        $this->patchJson("/api/v1/shop/shops/$shop", [...$input, 'name' => 'Updated test shop'])
            ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.status', 'pending');
        $this->asSession($admin)->postJson($review, $decision)->assertConflict();
        $this->postJson($review, ['decision' => 'verified', 'revision' => 2])->assertUnprocessable();
        $this->postJson($review, ['decision' => 'rejected', 'revision' => 2, 'reason' => 'Evidence required'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('audit_logs', ['target_id' => $shop, 'action' => 'shop.reviewed']);
    }

    public function test_incomplete_scan_does_not_block_other_quarantined_documents(): void
    {
        Storage::fake('local');
        Process::fake();
        config(['filesystems.shop_clamscan_binary' => PHP_BINARY]);
        $this->asSession($this->account('shopkeeper'));
        $shop = $this->postJson('/api/v1/shop/shops', $this->profile())->assertCreated()->json('data.id');
        $upload = "/api/v1/shop/shops/$shop/documents";

        foreach (['missing', 'changed', 'clean'] as $name) {
            $this->post($upload, [
                'document' => UploadedFile::fake()->createWithContent($name.'.pdf', "%PDF-1.4\n% $name\n%%EOF\n"),
            ], ['Accept' => 'application/json'])->assertCreated();
        }
        [$missing, $changed, $clean] = ShopDocument::where('shop_id', $shop)->orderBy('id')->get()->all();
        Storage::disk('local')->delete($missing->storage_key);
        Storage::disk('local')->put($changed->storage_key, 'changed after upload');

        $this->artisan('shops:scan-documents')->assertFailed();
        $this->assertSame('quarantined', $missing->fresh()->status);
        $this->assertSame('rejected', $changed->fresh()->status);
        $this->assertSame('available', $clean->fresh()->status);
        Process::assertRanTimes(fn ($process) => in_array('--alert-encrypted=yes', $process->command, true)
            && in_array('--alert-exceeds-max=yes', $process->command, true), 1);
    }

    private function profile(): array
    {
        $city = City::where('name_fr', 'Tanger')->firstOrFail();

        return ['name' => 'Test shop', 'region_id' => $city->region_id, 'city_id' => $city->id, 'contact_phone' => '+212612345678'];
    }

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->firstOrFail()->id, ['granted_at' => now()]);

        return $user->refresh();
    }

    private function asSession(User $user): self
    {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web')->withSession(['account_version' => $user->session_version]);
    }
}
