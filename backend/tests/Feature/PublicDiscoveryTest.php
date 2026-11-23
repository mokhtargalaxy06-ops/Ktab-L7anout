<?php

namespace Tests\Feature;

use App\Models\BeneficiaryProfile;
use App\Models\City;
use App\Models\DebtCase;
use App\Models\Region;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_filters_search_location_amount_status_and_paginates(): void
    {
        $rabat = $this->shop('Rabat', 'الرباط');
        $fes = $this->shop('Fès', 'فاس');
        for ($index = 0; $index < 13; $index++) {
            $this->debt($rabat, 25000 + $index);
        }
        $funded = $this->debt($fes, 49000);
        $this->fund($funded, 49000, 3000);
        $settled = $this->debt($fes, 73000);
        $this->fund($settled, 73000, 73000);

        $this->getJson('/api/v1/cases')->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('meta.total', 15);
        $this->getJson('/api/v1/cases?page=2')->assertJsonCount(3, 'data')->assertJsonPath('meta.page', 2);
        $query = http_build_query(['city_id' => $fes->city_id, 'region_id' => $fes->city->region_id, 'q' => 'فاس',
            'min_minor' => '40000', 'max_minor' => '50000', 'status' => 'funded']);
        $this->getJson('/api/v1/cases?'.$query)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $funded->public_reference)
            ->assertJsonPath('data.0.progress', 100)->assertJsonPath('data.0.status', 'funded');
        $this->getJson('/api/v1/cases?status=settled')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $settled->public_reference);
        $this->getJson('/api/v1/cases?status=open')->assertJsonPath('meta.total', 13);
        $this->getJson('/api/v1/cases?q='.rawurlencode(substr($funded->public_reference, 0, 12)))->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/cases?q=%25')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/cases?region_id=bad')->assertUnprocessable();
        $this->getJson('/api/v1/cases?min_minor=200&max_minor=100')->assertUnprocessable();
    }

    public function test_detail_is_anonymous_and_visibility_is_shared_with_home_and_catalog(): void
    {
        $shop = $this->shop('Visible', 'محل');
        $live = $this->debt($shop, 85001);
        $this->fund($live, 15000, 0);
        $live->history()->create(['actor_id' => User::factory()->create()->id, 'audience' => 'admin', 'action' => 'publish',
            'revision' => 1, 'reason' => 'Identity never public', 'created_at' => now()]);
        $approved = $this->debt($shop, 1000);
        $approved->forceFill(['status' => 'approved'])->save();
        $stale = $this->debt($shop, 1000);
        $stale->forceFill(['verified_shop_revision' => 999])->save();

        $this->getJson('/api/v1/cases/'.$live->public_reference)->assertOk()->assertJsonPath('data.remaining_minor', '70001')
            ->assertJsonPath('data.progress', 17)->assertJsonPath('data.updates.1.type', 'publish')
            ->assertJsonMissingPath('data.internal_id')->assertJsonMissingPath('data.debt_reference')
            ->assertJsonMissingPath('data.shop_id')->assertJsonMissingPath('data.situation')
            ->assertJsonMissingPath('data.documents')->assertJsonMissingPath('data.updates.1.reason');
        foreach ([$approved->public_reference, $stale->public_reference, $live->id] as $id) {
            $this->getJson('/api/v1/cases/'.$id)->assertNotFound();
        }
        $this->getJson('/api/v1/discovery')->assertJsonCount(1, 'data.recent');
        $shop->forceFill(['status' => 'suspended'])->save();
        $this->getJson('/api/v1/cases')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/cases/'.$live->public_reference)->assertNotFound();
    }

    public function test_urgency_uses_current_balance_assessed_grades_and_documented_ties(): void
    {
        $shop = $this->shop('Tanger', 'طنجة');
        $younger = $this->debt($shop, 500000, '2024-05-01', 4);
        $older = $this->debt($shop, 500000, '2024-01-01', 4);
        $lower = $this->debt($shop, 500000, '2024-01-01', 3);
        $this->debt($shop, 500000, '2023-01-01');
        $this->fund($lower, 500000, 0);

        $this->getJson('/api/v1/cases?urgent=1')->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $older->public_reference)->assertJsonPath('data.1.id', $younger->public_reference)
            ->assertJsonMissingPath('data.0.access_risk_grade');
        config(['discovery.urgency.urgent_threshold' => 100]);
        $this->fund($older, 400000, 0);
        $this->getJson('/api/v1/cases?urgent=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $younger->public_reference);
        $this->getJson('/api/v1/discovery')->assertJsonPath('data.urgency_policy.weights.vulnerability', 35)
            ->assertJsonPath('data.urgency_policy.urgent_threshold', 100);
    }

    public function test_statistics_aggregate_real_records_and_count_each_helped_family_once(): void
    {
        $this->getJson('/api/v1/discovery')->assertJsonPath('data.statistics', [
            'families_helped' => 0, 'fully_settled_cases' => 0, 'settled_minor' => '0', 'allocated_minor' => '0', 'received_minor' => '0', 'external_paid_minor' => '0', 'currency' => 'MAD',
        ]);
        $shop = $this->shop('Agadir', 'أكادير');
        $first = $this->debt($shop, 45000);
        $second = $this->debt($shop, 45000);
        $second->forceFill(['beneficiary_profile_id' => $first->beneficiary_profile_id])->save();
        $this->fund($first, 45000, 45000);
        $this->fund($second, 30000, 12000);
        $invalid = $this->debt($shop, 45000);
        $this->fund($invalid, 50000, 50000);
        $this->getJson('/api/v1/cases/'.$invalid->public_reference)->assertNotFound();
        DB::table('funding_receipt_reports')->insert(['receipt_id' => (string) Str::uuid(), 'received_minor' => 85000]);

        $this->getJson('/api/v1/discovery')->assertJsonPath('data.statistics', [
            'families_helped' => 1, 'fully_settled_cases' => 1, 'settled_minor' => '57000', 'allocated_minor' => '75000', 'received_minor' => '85000', 'external_paid_minor' => '0', 'currency' => 'MAD',
        ]);
    }

    public function test_assessment_is_private_and_requires_an_authorized_reviewer_and_current_revision(): void
    {
        $case = $this->debt($this->shop('Salé', 'سلا'), 500000);
        $path = "/api/v1/admin/cases/{$case->id}/discovery";
        $fields = ['revision' => 1, 'story' => 'income', 'vulnerability' => 4, 'access_risk' => 4, 'reason' => 'Private rationale'];
        $this->postJson($path, $fields)->assertUnauthorized();
        $user = User::factory()->create()->refresh();
        $this->actingAs($user, 'web')->withSession(['account_version' => $user->session_version])
            ->withHeader('Origin', 'http://127.0.0.1:5173');
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->postJson($path, $fields)->assertForbidden();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('code', 'administrator')->value('id'), ['granted_at' => now()]);
        Auth::forgetGuards();
        $admin->refresh();
        $this->actingAs($admin, 'web')->withSession(['account_version' => $admin->session_version]);
        $this->postJson($path, array_replace($fields, ['revision' => 2]))->assertConflict();
        $this->postJson($path, $fields)->assertOk();
        $this->getJson('/api/v1/cases/'.$case->public_reference)->assertJsonPath('data.story', 'income')->assertDontSee('Private rationale');
        $this->assertDatabaseHas('audit_logs', ['target_id' => $case->id, 'action' => 'case.public_assessed']);
        $metadata = DB::table('audit_logs')->where('target_id', $case->id)->where('action', 'case.public_assessed')->value('metadata');
        $this->assertSame(['public_story' => 'income', 'vulnerability_grade' => 4, 'access_risk_grade' => 4, 'revision' => 1],
            json_decode($metadata, true, flags: JSON_THROW_ON_ERROR));
    }

    private function shop(string $name, string $arabic): Shop
    {
        $attributes = ['code' => (string) Str::uuid(), 'name_fr' => $name, 'name_ar' => $arabic,
            'source_url' => 'https://example.test/source', 'source_date' => '2024-01-01'];
        $region = Region::create($attributes);
        $city = City::create($attributes + ['region_id' => $region->id]);
        $shop = Shop::create(['name' => 'Private shop', 'city_id' => $city->id, 'contact_phone' => '+212612345678']);
        $shop->forceFill(['status' => 'verified'])->save();

        return $shop->refresh();
    }

    private function debt(Shop $shop, int $amount, string $date = '2024-01-01', ?int $grade = null): DebtCase
    {
        $profile = BeneficiaryProfile::create(['user_id' => User::factory()->create()->id]);
        $case = new DebtCase(['city_id' => $shop->city_id, 'shop_id' => $shop->id, 'debt_reference' => 'SECRET',
            'debt_date' => $date, 'amount_minor' => $amount, 'situation' => 'Private story']);
        $case->profile()->associate($profile);
        $case->forceFill(['status' => 'published', 'published_at' => now()->subDay(), 'approved_minor' => $amount,
            'verified_shop_revision' => $shop->revision, 'vulnerability_grade' => $grade, 'access_risk_grade' => $grade])->save();

        return $case->refresh();
    }

    private function fund(DebtCase $case, int $allocated, int $settled): void
    {
        DB::table('case_funding_reports')->insert(['debt_case_id' => $case->id,
            'allocated_minor' => $allocated, 'settled_minor' => $settled, 'updated_at' => now()]);
    }
}
