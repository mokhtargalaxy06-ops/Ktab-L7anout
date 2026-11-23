<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SimulatedProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_simulation_is_opt_in_and_production_always_blocks_it(): void
    {
        config(['funding.simulator_enabled' => false]);
        $this->app->instance('env', 'staging');
        $this->assertFalse(SimulatedProvider::enabled());
        config(['funding.simulator_enabled' => true]);
        $this->assertTrue(SimulatedProvider::enabled());
        $this->app->instance('env', 'production');
        $this->assertFalse(SimulatedProvider::enabled());
    }

    public function test_operations_check_emits_aggregate_metrics(): void
    {
        $summary = json_encode([
            'failed_jobs' => 0, 'queued_jobs_over_5m' => 0, 'evidence_quarantined_over_30m' => 0,
            'shop_evidence_quarantined_over_30m' => 0, 'funding_frozen' => true,
            'negative_receipt_balances' => 0,
            'payouts_unresolved_over_1h' => 0, 'refunds_waiting_over_1h' => 0,
        ], JSON_THROW_ON_ERROR);
        DB::table('financial_control')->where('id', 1)->update(['frozen' => true]);
        $this->artisan('ops:check')->expectsOutput($summary)->assertSuccessful();
    }

    public function test_health_and_missing_routes_return_json(): void
    {
        $this->get('/api/health')->assertOk()->assertExactJson(['data' => ['status' => 'ok']]);
        $this->get('/api/missing')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_registration_normalizes_email_and_assigns_only_the_donor_role(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Test Account',
            'email' => 'TEST@example.com',
            'phone' => '0612345678',
            'password' => 'A long test password',
            'password_confirmation' => 'A long test password',
            'locale' => 'ar',
            'roles' => ['administrator'],
            'status' => 'suspended',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonPath('data.locale', 'ar')->assertJsonPath('data.roles', ['donor'])
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertTrue(Hash::check('A long test password', $user->password));
        $this->assertSame('active', $user->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_authentication_requires_a_session_and_rejects_suspended_accounts(): void
    {
        $this->get('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $user = User::factory()->create(['status' => 'suspended']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $origin = 'http://127.0.0.1:5173';
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->actingAs($user)->getJson('/api/v1/me', ['Origin' => $origin])->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_login_and_logout_update_the_session(): void
    {
        $user = User::factory()->create();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->getJson('/api/v1/me', ['Origin' => 'http://127.0.0.1:5173'])->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_cors_allows_only_the_configured_origins(): void
    {
        config(['cors.allowed_origins' => ['http://127.0.0.1:5173']]);
        $headers = ['Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'X-XSRF-TOKEN'];
        $this->options('/login', $headers + ['Origin' => 'http://127.0.0.1:5173'])
            ->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response = $this->options('/login', $headers + ['Origin' => 'https://example.net']);
        $this->assertNotSame('https://example.net', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_validation_uses_the_error_envelope_in_the_selected_language(): void
    {
        $this->postJson('/register', [], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('message', 'راجع المعلومات المدخلة.')
            ->assertJsonStructure(['request_id', 'errors' => ['email', 'password', 'locale']]);
    }

    public function test_login_limit_applies_to_the_normalized_account_across_ips(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.$attempt"])
                ->postJson('/login', ['email' => 'Account@example.com', 'password' => 'invalid-password'])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->postJson('/login', ['email' => 'account@example.com', 'password' => 'invalid-password'])
            ->assertTooManyRequests()->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
    }

    public function test_csrf_is_required_for_an_authentication_mutation(): void
    {
        $this->app->instance('env', 'local');

        $this->postJson('/login', ['email' => 'test@example.com', 'password' => 'unused'])
            ->assertStatus(419)->assertJsonPath('code', 'csrf_expired');
    }
}
