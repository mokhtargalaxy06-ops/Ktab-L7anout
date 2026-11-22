<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\DebtCase;
use App\Models\DebtDocument;
use App\Models\Region;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DebtApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $beneficiary;

    private User $owner;

    private User $admin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['127.0.0.1:5173'], 'filesystems.shop_clamscan_binary' => PHP_BINARY]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
        Storage::fake('local');
        Process::fake();
        $this->beneficiary = $this->account('beneficiary');
        $this->owner = $this->account('shopkeeper');
        $this->admin = $this->account('administrator');

        $fields = ['code' => 'test', 'name_fr' => 'Fixture', 'name_ar' => 'اختبار', 'source_url' => 'https://example.test/source', 'source_date' => '2024-01-01'];
        $region = Region::create($fields);
        $city = City::create($fields + ['region_id' => $region->id]);
        $this->shop = Shop::create(['name' => 'Test shop', 'city_id' => $city->id, 'contact_phone' => '+212612345678']);
        $this->shop->forceFill(['status' => 'verified'])->save();
        DB::table('shop_memberships')->insert(['shop_id' => $this->shop->id, 'user_id' => $this->owner->id, 'granted_at' => now()]);
    }

    public function test_application_is_published_only_after_confirmation_and_approval_and_is_anonymous(): void
    {
        $id = $this->draft();
        $base = "/api/v1/beneficiary/cases/$id";
        $this->asUser($this->admin);
        $this->getJson("/api/v1/admin/cases/$id")->assertNotFound();
        $this->asUser($this->beneficiary);
        $this->getJson('/api/v1/beneficiary/cases')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("$base/submit", ['consent' => true, 'consent_version' => '1', 'revision' => 1])->assertConflict();
        $doc = $this->upload($base);
        $this->get("$base/documents/$doc")->assertNotFound();
        $this->artisan('shops:scan-documents', ['--cases' => true])->assertSuccessful();
        $this->get("$base/documents/$doc")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson("$base/submit", ['consent' => false, 'consent_version' => '1', 'revision' => 1])->assertUnprocessable();
        $this->submit($id);
        $this->asUser($this->admin);
        $this->review($id, 'publish')->assertConflict();
        $this->review($id, 'approve')->assertConflict();
        $this->review($id, 'route')->assertOk()->assertJsonPath('data.status', 'pending_verification');
        $this->asUser($this->owner);
        $shopBase = "/api/v1/shop/cases/$id";
        $this->getJson('/api/v1/shop/cases')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($shopBase)->assertOk()->assertJsonMissingPath('data.situation')->assertJsonCount(0, 'data.documents');
        $this->get("$shopBase/documents/$doc")->assertNotFound();
        $ownEvidence = $this->upload($shopBase);
        $this->artisan('shops:scan-documents', ['--cases' => true])->assertSuccessful();
        $this->get("$shopBase/documents/$ownEvidence")->assertOk();
        $this->verify($id, 'confirm', '22000')->assertUnprocessable();
        $this->verify($id, 'confirm')->assertOk()->assertJsonPath('data.status', 'under_review');
        $this->asUser($this->admin);
        $this->getJson("/api/v1/admin/cases/$id")->assertOk()->assertJsonCount(2, 'data.documents')->assertJsonFragment(['action' => 'confirm']);
        $this->review($id, 'flag', 'Review required')->assertOk()->assertJsonPath('data.flagged', true);
        $this->review($id, 'approve')->assertConflict();
        $this->review($id, 'clear_flag', 'Checked')->assertOk();
        $this->review($id, 'approve')->assertOk()->assertJsonPath('data.status', 'approved');
        $this->getJson('/api/v1/cases')->assertJsonCount(0, 'data');
        $this->review($id, 'publish')->assertOk()->assertJsonPath('data.status', 'published');
        $this->getJson('/api/v1/cases')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', DebtCase::findOrFail($id)->public_reference)
            ->assertJsonPath('data.0.amount_minor', '25000')
            ->assertJsonMissingPath('data.0.situation')->assertJsonMissingPath('data.0.debt_reference');
        $this->assertDatabaseHas('audit_logs', ['target_id' => $id, 'action' => 'case.publish']);
        $this->shop->forceFill(['status' => 'suspended'])->save();
        $this->getJson('/api/v1/cases')->assertJsonCount(0, 'data');
    }

    public function test_dispute_request_for_information_rejection_and_cancellation(): void
    {
        $id = $this->draft();
        $this->submitWithEvidence($id);
        $this->asUser($this->admin);
        $this->review($id, 'route')->assertOk();
        $this->asUser($this->owner);
        $this->verify($id, 'dispute', '22000')->assertUnprocessable();
        $this->verify($id, 'dispute', '22000', 'Amount differs')->assertOk()->assertJsonPath('data.status', 'disputed');
        $this->verify($id, 'confirm')->assertConflict();
        $this->asUser($this->admin);
        $this->review($id, 'request_info', 'New statement required')->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.status', 'draft');
        $this->asUser($this->beneficiary);
        $this->postJson("/api/v1/beneficiary/cases/$id/submit", ['consent' => true, 'consent_version' => '1', 'revision' => 2])->assertConflict();
        $this->post("/api/v1/beneficiary/cases/$id/documents", [
            'revision' => 1,
            'document' => UploadedFile::fake()->createWithContent('stale.pdf', "%PDF-1.4\n%%EOF\n"),
        ], ['Accept' => 'application/json'])->assertConflict();
        $this->submitWithEvidence($id, 2);
        $this->asUser($this->admin);
        $this->review($id, 'route', revision: 2)->assertOk();
        $this->asUser($this->owner);
        $this->verify($id, 'reject', null, 'Unknown debt', 2)->assertOk()->assertJsonPath('data.status', 'disputed');
        $this->asUser($this->admin);
        $this->review($id, 'reject', 'Evidence insufficient', 2)->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->review($id, 'route', revision: 2)->assertConflict();

        $second = $this->draft('other');
        $this->postJson("/api/v1/beneficiary/cases/$second/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/beneficiary/cases/$second/submit", ['consent' => true, 'consent_version' => '1', 'revision' => 1])->assertConflict();
    }

    public function test_access_is_scoped_and_potential_duplicate_is_visible_only_to_admin(): void
    {
        $id = $this->draft();
        $doc = $this->submitWithEvidence($id);
        $duplicate = $this->draft();
        $this->submitWithEvidence($duplicate);
        $this->asUser($this->account('beneficiary'));
        $this->getJson("/api/v1/beneficiary/cases/$id")->assertForbidden();
        $this->get("/api/v1/beneficiary/cases/$id/documents/$doc")->assertForbidden();
        $this->asUser($this->beneficiary);
        $this->review($id, 'approve')->assertForbidden();
        $this->verify($id, 'confirm')->assertForbidden();
        $this->asUser($this->admin);
        $this->getJson('/api/v1/admin/cases')->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/admin/cases/$id")->assertOk()->assertJsonPath('data.duplicate_count', 1)
            ->assertJsonPath('data.duplicates.0.id', $duplicate);
        $this->review($id, 'route')->assertOk();
        $this->asUser($this->account('shopkeeper'));
        $this->getJson("/api/v1/shop/cases/$id")->assertForbidden();
        $this->verify($id, 'confirm')->assertForbidden();
        $this->asUser($this->owner);
        $this->getJson("/api/v1/shop/cases/$id/documents/$doc")->assertNotFound();
        $this->asUser($this->beneficiary);
        $this->getJson("/api/v1/beneficiary/cases/$id")->assertJsonMissingPath('data.duplicate_count');
        Auth::forgetGuards();
        $this->flushSession();
        $this->getJson("/api/v1/beneficiary/cases/$id/documents/$doc")->assertUnauthorized();
    }

    private function draft(string $reference = 'a1'): string
    {
        $this->asUser($this->beneficiary);

        return $this->postJson('/api/v1/beneficiary/cases', [
            'city_id' => $this->shop->city_id, 'shop_id' => $this->shop->id, 'debt_reference' => $reference,
            'debt_date' => '2024-01-01', 'amount_minor' => '25000', 'situation' => 'Private explanation', 'household_size' => 4,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
    }

    private function upload(string $base, int $revision = 1): string
    {
        $response = $this->post("$base/documents", [
            'revision' => $revision,
            'document' => UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\n%%EOF\n"),
        ], ['Accept' => 'application/json'])->assertCreated();

        return collect($response->json('data.documents'))->last()['id'];
    }

    private function submitWithEvidence(string $id, int $revision = 1): string
    {
        $doc = $this->upload("/api/v1/beneficiary/cases/$id", $revision);
        $this->artisan('shops:scan-documents', ['--cases' => true])->assertSuccessful();
        $this->assertSame('available', DebtDocument::findOrFail($doc)->status);
        $this->postJson("/api/v1/beneficiary/cases/$id/submit", ['consent' => true, 'consent_version' => '1', 'revision' => $revision - 1])
            ->assertStatus($revision === 1 ? 422 : 409);
        $this->submit($id, $revision);

        return $doc;
    }

    private function submit(string $id, int $revision = 1): void
    {
        $this->postJson("/api/v1/beneficiary/cases/$id/submit", ['consent' => true, 'consent_version' => '1', 'revision' => $revision])
            ->assertOk()->assertJsonPath('data.status', 'submitted');
    }

    private function review(string $id, string $decision, ?string $reason = null, int $revision = 1)
    {
        return $this->postJson("/api/v1/admin/cases/$id/review", compact('decision', 'reason', 'revision'));
    }

    private function verify(string $id, string $decision, ?string $amount = '25000', ?string $reason = null, int $revision = 1)
    {
        return $this->postJson("/api/v1/shop/cases/$id/verification", ['amount_minor' => $amount] + compact('decision', 'reason', 'revision'));
    }

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->firstOrFail()->id, ['granted_at' => now()]);

        return $user->refresh();
    }

    private function asUser(User $user): void
    {
        Auth::forgetGuards();
        $this->actingAs($user, 'web')->withSession(['account_version' => $user->session_version]);
    }
}
