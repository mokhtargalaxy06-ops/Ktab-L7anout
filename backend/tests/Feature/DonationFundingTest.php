<?php

namespace Tests\Feature;

use App\Models\BeneficiaryProfile;
use App\Models\City;
use App\Models\DebtCase;
use App\Models\Region;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationFundingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['funding.simulator_enabled' => true, 'funding.simulator_secret' => str_repeat('test-', 8),
            'sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
        $this->actAs('donor');
    }

    public function test_specific_confirmation_rechecks_capacity_and_refunds_only_the_excess(): void
    {
        $case = $this->case(10000);
        $other = $this->case(90000);
        $first = $this->create(7000, $case);
        $second = $this->create(7000, $case);
        $this->getJson('/api/v1/donations')->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/donations/$first")->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.captured_minor', '0');
        $this->assertDatabaseCount('financial_events', 0);
        $this->simulate($first, 'success')->assertJsonPath('data.allocated_minor', '7000');
        $this->simulate($second, 'success')->assertJsonPath('data.allocated_minor', '3000')
            ->assertJsonPath('data.return_pending_minor', '4000')->assertJsonPath('data.returned_minor', '0')
            ->assertJsonPath('data.allocations.0.case_reference', $case->public_reference);
        $this->assertDatabaseMissing('donation_allocations', ['debt_case_id' => $other->id]);
        $this->artisan('funding:process')->assertSuccessful();
        $this->getJson("/api/v1/donations/$second")->assertJsonPath('data.returned_minor', '4000')
            ->assertJsonPath('data.return_pending_minor', '0');
        $this->getJson('/api/v1/discovery')->assertJsonPath('data.statistics.received_minor', '14000')
            ->assertJsonPath('data.statistics.allocated_minor', '10000')->assertJsonPath('data.simulated', true);
        config(['funding.simulator_enabled' => false]);
        $this->getJson('/api/v1/discovery')->assertJsonPath('data.statistics.received_minor', '0')
            ->assertJsonPath('data.statistics.allocated_minor', '0')->assertJsonPath('data.simulated', false);
    }

    public function test_outcomes_are_transparent_and_owner_scoped(): void
    {
        $failed = $this->create(500);
        $cancelled = $this->create(500);
        $this->simulate($failed, 'pending')->assertJsonPath('data.payment_status', 'pending');
        $this->simulate($failed, 'failed')->assertJsonPath('data.payment_status', 'failed')->assertJsonPath('data.captured_minor', '0');
        $this->simulate($failed, 'success')->assertConflict();
        $this->simulate($cancelled, 'cancelled')->assertJsonPath('data.payment_status', 'cancelled');
        $this->assertDatabaseCount('financial_events', 0);
        $this->actAs('donor');
        $this->getJson("/api/v1/donations/$failed")->assertForbidden();
        $this->postJson("/api/v1/donations/$cancelled/simulation", ['outcome' => 'success'])->assertForbidden();
    }

    public function test_intent_is_idempotent_and_rejects_invalid_scope_amount_and_case(): void
    {
        $case = $this->case(1000);
        $this->intent(1001, $case)->assertConflict();
        $this->intent(100, $case, ['amount_minor' => 100])->assertUnprocessable();
        $this->intent(100, $case, ['currency' => 'USD'])->assertUnprocessable();
        $this->intent(100, $case, ['scope' => 'urgent_pool'])->assertUnprocessable();
        $id = $this->intent(100, $case, key: 'same')->assertCreated()->json('data.id');
        $this->intent(100, $case, key: 'same')->assertCreated()->assertJsonPath('data.id', $id);
        $this->intent(101, $case, key: 'same')->assertConflict();
        $case->forceFill(['status' => 'approved'])->save();
        $this->intent(100, $case)->assertConflict();
        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->expectException(QueryException::class);
        DB::table('donations')->where('id', $id)->delete();
    }

    public function test_signed_webhooks_are_authenticated_idempotent_and_use_server_amount(): void
    {
        $case = $this->case(10000);
        $donation = $this->create(5000, $case);
        $this->simulate($donation, 'cancelled')->assertOk();
        $payment = DB::table('payment_transactions')->where('donation_id', $donation)->first();
        $payload = ['event_id' => 'test-event', 'reference' => $payment->provider_reference, 'amount_minor' => '5000', 'currency' => 'MAD', 'outcome' => 'success'];
        $this->webhook($payload, signed: false)->assertUnauthorized();
        $this->webhook($payload, timestamp: now()->subMinutes(6)->timestamp)->assertUnauthorized();
        $unsignedOutcome = $payload;
        unset($unsignedOutcome['outcome']);
        $this->webhook($unsignedOutcome, query: '?outcome=success')->assertUnprocessable();
        $this->webhook(array_replace($payload, ['amount_minor' => '9000']))->assertUnprocessable();
        $this->webhook($payload)->assertAccepted();
        $this->webhook($payload)->assertAccepted();
        $this->webhook(array_replace($payload, ['event_id' => 'duplicate-capture']))->assertAccepted();
        $this->webhook(array_replace($payload, ['outcome' => 'failed']))->assertConflict();
        $this->assertDatabaseCount('donation_allocations', 1);
        $this->assertDatabaseCount('funding_receipt_reports', 1);
        $this->assertSame(1, DB::table('financial_events')->where('type', 'receipt_confirmed')->count());
        $this->getJson("/api/v1/donations/$donation")->assertJsonPath('data.payment_status', 'confirmed');
    }

    public function test_urgent_pool_splits_by_rank_and_expires_its_residual_at_the_deadline(): void
    {
        $first = $this->case(5000, 4, '2023-01-01');
        $second = $this->case(9000, 4, '2023-02-01');
        $unassessed = $this->case(20000, null);
        $donation = $this->create(18000);
        $this->simulate($donation, 'success')->assertJsonPath('data.allocations.0.case_reference', $second->public_reference)
            ->assertJsonPath('data.allocations.1.case_reference', $first->public_reference)->assertJsonPath('data.unallocated_minor', '4000');
        $this->assertDatabaseMissing('donation_allocations', ['debt_case_id' => $unassessed->id]);
        $this->artisan('funding:process')->assertSuccessful();
        $count = DB::table('allocation_runs')->count();
        $this->artisan('funding:process')->assertSuccessful();
        $this->assertDatabaseCount('allocation_runs', $count);
        $this->travel(30)->days();
        $this->artisan('funding:process')->assertSuccessful();
        $this->getJson("/api/v1/donations/$donation")->assertJsonPath('data.returned_minor', '4000')
            ->assertJsonPath('data.allocated_minor', '14000')->assertJsonPath('data.unallocated_minor', '0');
    }

    public function test_refunds_and_reversals_release_unspent_funds_and_preserve_journal_history(): void
    {
        $case = $this->case(10000);
        $donation = $this->create(10000, $case);
        $this->simulate($donation, 'success')->assertOk();
        $path = "/api/v1/admin/donations/$donation/simulate-return";
        $this->postJson($path, ['kind' => 'refund', 'current_password' => 'password', 'reason' => 'Refund fixture', 'amount_minor' => '2500'], ['Idempotency-Key' => 'return-1'])->assertForbidden();
        $this->actAs('administrator');
        $this->postJson($path, ['kind' => 'refund', 'current_password' => 'password', 'reason' => 'Refund fixture', 'amount_minor' => '2500'], ['Idempotency-Key' => 'return-1'])->assertNoContent();
        $this->postJson($path, ['kind' => 'refund', 'current_password' => 'password', 'reason' => 'Refund fixture', 'amount_minor' => '2500'], ['Idempotency-Key' => 'return-1'])->assertNoContent();
        $this->postJson($path, ['kind' => 'reversal', 'current_password' => 'password', 'reason' => 'Reversal fixture', 'amount_minor' => '7000'], ['Idempotency-Key' => 'reverse-1'])->assertNoContent();
        $this->postJson($path, ['kind' => 'reversal', 'current_password' => 'password', 'reason' => 'Reversal fixture', 'amount_minor' => '501'], ['Idempotency-Key' => 'reverse-too-much'])->assertConflict();
        $this->assertDatabaseCount('refunds', 2);
        $this->assertDatabaseHas('donation_allocations', ['amount_minor' => 10000]);
        $this->assertDatabaseHas('case_funding_reports', ['debt_case_id' => $case->id, 'allocated_minor' => 500]);
        $this->assertSame(2, DB::table('financial_events')->where('type', 'allocation_released')->count());
        $this->expectException(QueryException::class);
        DB::table('financial_events')->update(['amount_minor' => 1]);
    }

    public function test_reversal_freezes_allocation_when_payout_funds_are_already_held(): void
    {
        $case = $this->case(10000);
        $donation = $this->create(10000, $case);
        $this->simulate($donation, 'success')->assertOk();
        $allocation = DB::table('donation_allocations')->first();
        $operator = User::factory()->create();
        $payout = (string) Str::uuid();
        DB::table('shop_payments')->insert([
            'id' => $payout, 'debt_case_id' => $case->id, 'initiator_id' => $operator->id,
            'operation_key' => 'fixture-payout', 'destination_reference' => 'simulation-fixture',
            'amount_minor' => 10000, 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shop_payment_lines')->insert([
            'shop_payment_id' => $payout, 'donation_allocation_id' => $allocation->id, 'amount_minor' => 10000,
        ]);
        $this->getJson("/api/v1/donations/$donation")->assertJsonPath('data.allocations.0.settlements.0.status', 'submitted');
        $this->actAs('administrator');
        $url = "/api/v1/admin/donations/$donation/simulate-return";
        $this->postJson($url, ['kind' => 'refund', 'current_password' => 'password', 'reason' => 'Refund fixture', 'amount_minor' => '100'], ['Idempotency-Key' => 'refund-blocked'])
            ->assertConflict();
        $this->postJson($url, ['kind' => 'reversal', 'current_password' => 'password', 'reason' => 'Reversal fixture', 'amount_minor' => '4000'], ['Idempotency-Key' => 'reverse-held'])
            ->assertNoContent();
        $this->assertTrue((bool) DB::table('financial_control')->where('id', 1)->value('frozen'));
        $this->assertDatabaseHas('shop_payments', ['id' => $payout, 'status' => 'submitted']);
        $this->assertDatabaseHas('case_funding_reports', ['debt_case_id' => $case->id, 'allocated_minor' => 10000]);
        $this->actAs('donor');
        $another = $this->create(100, $this->case(10000));
        $this->simulate($another, 'success')->assertJsonPath('data.captured_minor', '100')
            ->assertJsonPath('data.allocated_minor', '0');
    }

    public function test_partially_reversed_pool_receipt_can_still_fund_cases(): void
    {
        $donation = $this->create(5000);
        $this->simulate($donation, 'success')->assertOk();
        $this->actAs('administrator');
        $this->postJson("/api/v1/admin/donations/$donation/simulate-return", ['kind' => 'reversal', 'current_password' => 'password', 'reason' => 'Reversal fixture', 'amount_minor' => '1000'],
            ['Idempotency-Key' => 'partial-pool-reversal'])->assertNoContent();
        $this->case(10000);
        $this->artisan('funding:process')->assertSuccessful();
        $payment = DB::table('payment_transactions')->where('donation_id', $donation)->first();
        $this->assertDatabaseHas('donation_allocations', ['payment_transaction_id' => $payment->id, 'amount_minor' => 4000]);
    }

    public function test_partial_reversal_preserves_unaffected_specific_excess_refund(): void
    {
        $case = $this->case(10000);
        $first = $this->create(7000, $case);
        $second = $this->create(7000, $case);
        $this->simulate($first, 'success')->assertOk();
        $this->simulate($second, 'success')->assertJsonPath('data.return_pending_minor', '4000');
        $this->actAs('administrator');
        $path = "/api/v1/admin/donations/$second/simulate-return";
        $payload = ['kind' => 'reversal', 'current_password' => 'password', 'reason' => 'Reversal fixture', 'amount_minor' => '1000'];
        $headers = ['Idempotency-Key' => 'partial-excess-reversal'];
        $this->postJson($path, $payload, $headers)->assertNoContent();
        $this->postJson($path, $payload, $headers)->assertNoContent();
        $this->assertDatabaseHas('refunds', ['status' => 'canceled', 'amount_minor' => 4000]);
        $this->assertDatabaseHas('refunds', ['status' => 'requested', 'amount_minor' => 3000, 'reason' => 'specific_excess']);
        $this->artisan('funding:process')->assertSuccessful();
        $this->assertDatabaseHas('refunds', ['status' => 'confirmed', 'amount_minor' => 3000, 'kind' => 'refund']);
        $this->assertDatabaseCount('refunds', 3);
    }

    public function test_settlement_requires_separate_approval_shop_confirmation_and_reconciliation(): void
    {
        $case = $this->case(5000);
        $this->simulate($this->create(5000, $case), 'success')->assertOk();
        $this->assertDatabaseHas('settlement_queue', ['debt_case_id' => $case->id]);
        $this->actAs('administrator');
        $path = "/api/v1/admin/settlements/$case->id";
        $this->settlementAction($path, 'initiate', 'first')->assertNoContent();
        $this->settlementAction($path, 'initiate', 'first')->assertNoContent();
        $this->assertDatabaseCount('shop_payments', 1);
        $payment = DB::table('shop_payments')->value('id');
        $duplicate = (array) DB::table('shop_payments')->find($payment);
        $duplicate['id'] = (string) Str::uuid();
        $duplicate['operation_key'] = 'duplicate-active-payout';
        try {
            DB::transaction(fn () => DB::table('shop_payments')->insert($duplicate));
            $this->fail('The database accepted a second active payout.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0], ['23000', '23505']);
        }
        $this->settlementAction("$path/$payment", 'submit')->assertForbidden();
        $this->actAs('administrator');
        $this->settlementAction("$path/$payment", 'submit')->assertNoContent();
        $this->settlementAction("$path/$payment", 'transfer')->assertNoContent();
        $this->settlementAction("$path/$payment", 'reconcile')->assertConflict();
        $this->getJson('/api/v1/transparency')->assertJsonPath('data.received_minor', '5000')
            ->assertJsonPath('data.allocated_minor', '5000')->assertJsonPath('data.transferred_minor', '5000')
            ->assertJsonPath('data.confirmed_minor', '0')->assertJsonPath('data.debts_settled', 0);

        $owner = $this->actAs('shopkeeper');
        $shopPath = "/api/v1/shop/settlements/$case->id/$payment";
        $this->getJson('/api/v1/shop/settlements')->assertJsonCount(0, 'data');
        $this->settlementAction($shopPath, 'confirm', admin: false)->assertForbidden();
        DB::table('shop_memberships')->insert(['shop_id' => $case->shop_id, 'user_id' => $owner->id, 'granted_at' => now()]);
        $this->getJson('/api/v1/shop/settlements')->assertJsonPath('data.0.payments.0.reference', 'SIM-PAYOUT-'.$payment);
        $this->settlementAction($shopPath, 'confirm', admin: false)->assertNoContent();
        $this->getJson('/api/v1/transparency')->assertJsonPath('data.confirmed_minor', '5000')->assertJsonPath('data.debts_settled', 0);
        $this->settlementAction($shopPath, 'dispute', admin: false)->assertNoContent();
        $current = DB::table('settlement_response_heads')->where('shop_payment_id', $payment)->value('settlement_action_id');
        $this->assertDatabaseHas('settlement_actions', ['id' => $current, 'action' => 'dispute']);
        $dispute = DB::table('settlement_actions')->find($current);
        DB::table('settlement_actions')->insert([
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff', 'shop_payment_id' => $payment, 'actor_id' => $owner->id,
            'action' => 'confirm', 'operation_key' => 'legacy-tied-confirmation', 'request_hash' => str_repeat('a', 64),
            'created_at' => $dispute->created_at,
        ]);
        $migration = require database_path('migrations/2026_11_23_003000_track_current_shop_settlement_response.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('settlement_response_heads', ['shop_payment_id' => $payment, 'settlement_action_id' => $current]);
        $this->getJson('/api/v1/transparency')->assertJsonPath('data.confirmed_minor', '0');
        $this->get('/api/v1/admin/finance/journal.csv')->assertForbidden();
        $this->actAs('administrator');
        $this->getJson('/api/v1/admin/settlements?status=dispute')->assertJsonPath('data.0.id', $case->id);
        $this->settlementAction("$path/$payment", 'reconcile')->assertConflict();
        Auth::forgetGuards();
        $this->actingAs($owner, 'web')->withSession(['account_version' => $owner->session_version]);
        $this->settlementAction($shopPath, 'confirm', admin: false)->assertNoContent();
        $this->actAs('administrator');
        $this->settlementAction("$path/$payment", 'reconcile')->assertNoContent();
        $this->getJson('/api/v1/transparency')->assertJsonPath('data.debts_settled', 1)
            ->assertJsonPath('data.families_helped', 1)->assertJsonPath('data.available_minor', '0')
            ->assertJsonPath('data.regions.0.settled_minor', '5000');
        $this->getJson('/api/v1/admin/finance')->assertJsonPath('data.transactions.0.type', 'payout_evidenced');
        $csv = $this->get('/api/v1/admin/finance/journal.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('payout_transferred,5000', $csv);
        $this->assertStringContainsString('payout_evidenced,5000', $csv);
        $this->assertStringContainsString($payment, $csv);
        $report = $this->get('/api/v1/admin/finance/reconciliation.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('5000,0,5000,5000,0,0', $report);
    }

    public function test_payout_operators_and_shop_owners_cannot_act_on_both_sides(): void
    {
        $case = $this->case(5000);
        $this->simulate($this->create(5000, $case), 'success')->assertOk();
        $operator = $this->actAs('administrator');
        $path = "/api/v1/admin/settlements/$case->id";
        $this->settlementAction($path, 'initiate')->assertNoContent();
        $payment = DB::table('shop_payments')->value('id');
        $this->actAs('administrator');
        $this->settlementAction("$path/$payment", 'submit')->assertNoContent();
        $this->settlementAction("$path/$payment", 'transfer')->assertNoContent();
        $operator->roles()->attach(Role::where('code', 'shopkeeper')->value('id'), ['granted_at' => now()]);
        DB::table('shop_memberships')->insert(['shop_id' => $case->shop_id, 'user_id' => $operator->id, 'granted_at' => now()]);
        Auth::forgetGuards();
        $this->actingAs($operator, 'web')->withSession(['account_version' => $operator->session_version]);
        $this->settlementAction("/api/v1/shop/settlements/$case->id/$payment", 'confirm', admin: false)->assertForbidden();
        $this->settlementAction("$path/$payment", 'reconcile')->assertForbidden();
        $this->assertDatabaseMissing('settlement_actions', ['shop_payment_id' => $payment, 'action' => 'confirm']);
    }

    public function test_failed_attempt_can_retry_and_a_reversal_releases_unsubmitted_settlement(): void
    {
        $case = $this->case(5000);
        $donation = $this->create(5000, $case);
        $this->simulate($donation, 'success')->assertOk();
        $this->actAs('administrator');
        $path = "/api/v1/admin/settlements/$case->id";
        $this->settlementAction($path, 'initiate')->assertNoContent();
        $first = DB::table('shop_payments')->value('id');
        $this->actAs('administrator');
        $this->settlementAction("$path/$first", 'submit')->assertNoContent();
        $this->settlementAction("$path/$first", 'fail')->assertNoContent();
        $this->assertDatabaseHas('shop_payments', ['id' => $first, 'status' => 'failed']);
        $this->settlementAction($path, 'initiate')->assertNoContent();
        $this->postJson("/api/v1/admin/donations/$donation/simulate-return", ['kind' => 'reversal', 'current_password' => 'password', 'reason' => 'Reversal fixture', 'amount_minor' => '1000'],
            ['Idempotency-Key' => 'reversal-before-submission'])->assertNoContent();
        $this->assertDatabaseHas('shop_payments', ['status' => 'canceled']);
        $this->assertDatabaseHas('financial_control', ['id' => 1, 'frozen' => false]);
        $this->assertDatabaseHas('case_funding_reports', ['debt_case_id' => $case->id, 'allocated_minor' => 4000]);
        $this->settlementAction($path, 'initiate')->assertConflict();
        $this->getJson('/api/v1/transparency')->assertJsonPath('data.transferred_minor', '0');
    }

    public function test_recovery_releases_shortfall_after_a_submitted_transfer_conclusively_fails(): void
    {
        $case = $this->case(5000);
        $donation = $this->create(5000, $case);
        $this->simulate($donation, 'success')->assertOk();
        $this->actAs('administrator');
        $path = "/api/v1/admin/settlements/$case->id";
        $this->settlementAction($path, 'initiate')->assertNoContent();
        $payment = DB::table('shop_payments')->value('id');
        $this->actAs('administrator');
        $this->settlementAction("$path/$payment", 'submit')->assertNoContent();
        $this->postJson("/api/v1/admin/donations/$donation/simulate-return", ['kind' => 'reversal', 'current_password' => 'password', 'reason' => 'Reversal fixture', 'amount_minor' => '1000'],
            ['Idempotency-Key' => 'reversal-after-submission'])->assertNoContent();
        $this->getJson('/api/v1/admin/finance')->assertJsonPath('data.frozen', true)->assertJsonCount(1, 'data.deficits');
        $this->settlementAction("$path/$payment", 'recover')->assertConflict();
        $this->settlementAction("$path/$payment", 'fail')->assertNoContent();
        $this->settlementAction("$path/$payment", 'recover', 'restore-funding')->assertNoContent();
        $this->settlementAction("$path/$payment", 'recover', 'restore-funding')->assertNoContent();
        $this->getJson('/api/v1/admin/finance')->assertJsonPath('data.frozen', false)->assertJsonCount(0, 'data.deficits');
        $this->getJson('/api/v1/transparency')->assertJsonPath('data.allocated_minor', '4000')->assertJsonPath('data.transferred_minor', '0');
    }

    public function test_admin_reports_and_campaign_suspension_preserve_existing_funding(): void
    {
        $case = $this->case(5000);
        $id = $this->create(5000, $case);
        $this->simulate($id, 'success')->assertOk();
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->getJson('/api/v1/admin/financial-search/donations')->assertForbidden();
        $this->getJson('/api/v1/admin/audit')->assertForbidden();
        $this->actAs('administrator');
        $this->getJson('/api/v1/admin/overview')->assertOk()->assertJsonPath('data.published', 1)
            ->assertJsonPath('data.funded', 1)->assertJsonPath('data.donations_minor', '5000')->assertJsonPath('data.settlements', 1);
        $this->getJson('/api/v1/admin/financial-search/donations?id='.$id)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount_minor', '5000')->assertJsonPath('data.0.payment_status', 'confirmed')
            ->assertJsonMissingPath('data.0.donor_id');
        $this->getJson('/api/v1/admin/financial-search/allocations')->assertOk()->assertJsonPath('data.0.net_minor', '5000');
        $this->getJson('/api/v1/admin/financial-search/transactions')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/financial-search/refunds')->assertOk()->assertJsonCount(0, 'data');
        $region = City::find($case->city_id)->region_id;
        $this->getJson('/api/v1/admin/regions/activity?region_id='.$region)->assertOk()
            ->assertJsonPath('data.cities.data.0.id', $case->city_id)->assertJsonPath('data.cities.data.0.verified_shops', 1);
        $this->getJson('/api/v1/admin/regions/activity?coverage=uncovered')->assertOk()->assertJsonCount(0, 'data.cities.data');
        $review = "/api/v1/admin/cases/$case->id/review";
        $this->postJson($review, ['decision' => 'suspend', 'revision' => $case->revision])->assertUnprocessable();
        $this->postJson($review, ['decision' => 'suspend', 'revision' => $case->revision, 'reason' => 'Fixture investigation'])->assertOk();
        $this->getJson("/api/v1/cases/$case->public_reference")->assertNotFound();
        $this->postJson($review, ['decision' => 'publish', 'revision' => $case->revision])->assertConflict();
        $this->postJson($review, ['decision' => 'request_info', 'revision' => $case->revision, 'reason' => 'Fixture investigation'])->assertConflict();
        $this->assertDatabaseHas('case_funding_reports', ['debt_case_id' => $case->id, 'allocated_minor' => 5000]);
        $audit = $this->getJson('/api/v1/admin/audit?target_id='.$case->id)->assertOk()
            ->assertJsonPath('data.0.action', 'case.suspend')->assertJsonPath('data.0.changes.from', 'published')
            ->assertJsonPath('data.0.changes.to', 'approved')->assertJsonPath('data.0.changes.reason', 'Fixture investigation')->json('data.0.id');
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('audit_logs')->where('id', $audit)->delete());
    }

    private function settlementAction(string $path, string $action, ?string $key = null, bool $admin = true)
    {
        $data = ['action' => $action];
        if ($admin) {
            $data['password'] = 'password';
        }
        if (in_array($action, ['dispute', 'fail'], true)) {
            $data['reason'] = 'Simulation fixture';
        }

        return $this->postJson($path, $data, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function intent(int $amount, ?DebtCase $case, array $overrides = [], ?string $key = null)
    {
        return $this->postJson('/api/v1/donations', array_replace([
            'scope' => $case ? 'specific' : 'urgent_pool', 'case_reference' => $case?->public_reference,
            'amount_minor' => (string) $amount, 'currency' => 'MAD', 'policy_version' => 'mvp-1',
        ], $overrides), ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    private function create(int $amount, ?DebtCase $case = null): string
    {
        return $this->intent($amount, $case)->assertCreated()->json('data.id');
    }

    private function simulate(string $donation, string $outcome)
    {
        return $this->postJson("/api/v1/donations/$donation/simulation", compact('outcome'));
    }

    private function webhook(array $payload, bool $signed = true, ?int $timestamp = null, string $query = '')
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $time = $timestamp ?? now()->timestamp;

        return $this->call('POST', '/api/v1/webhooks/payments/simulator'.$query, [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SIMULATOR_TIMESTAMP' => $time,
            'HTTP_X_SIMULATOR_SIGNATURE' => $signed ? hash_hmac('sha256', $time.'.'.$body, config('funding.simulator_secret')) : 'bad',
        ], $body);
    }

    private function actAs(string $role): User
    {
        $user = User::factory()->create()->refresh();
        $user->roles()->attach(Role::where('code', $role)->value('id'), ['granted_at' => now()]);
        Auth::forgetGuards();
        $this->actingAs($user, 'web')->withSession(['account_version' => $user->session_version]);

        return $user;
    }

    private function case(int $amount, ?int $grade = 4, string $date = '2023-01-01'): DebtCase
    {
        $attributes = ['code' => (string) Str::uuid(), 'name_fr' => 'Fixture', 'name_ar' => 'مدينة',
            'source_url' => 'https://example.test/source', 'source_date' => '2024-01-01'];
        $region = Region::create($attributes);
        $city = City::create($attributes + ['region_id' => $region->id]);
        $shop = Shop::create(['name' => 'Fixture shop', 'city_id' => $city->id, 'contact_phone' => '+212612345678']);
        $shop->forceFill(['status' => 'verified'])->save();
        $profile = BeneficiaryProfile::create(['user_id' => User::factory()->create()->id]);
        $case = new DebtCase(['city_id' => $city->id, 'shop_id' => $shop->id, 'debt_reference' => 'TEST',
            'debt_date' => $date, 'amount_minor' => $amount, 'situation' => 'Fixture']);
        $case->profile()->associate($profile);
        $case->forceFill(['status' => 'published', 'published_at' => now()->subDay(), 'approved_minor' => $amount,
            'verified_shop_revision' => 1, 'vulnerability_grade' => $grade, 'access_risk_grade' => $grade])->save();

        return $case->refresh();
    }
}
