<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
        Notification::fake();
    }

    public function test_public_role_selection_and_administrator_rejection(): void
    {
        foreach (['donor', 'beneficiary', 'shopkeeper', 'administrator'] as $role) {
            $response = $this->postJson('/register', [
                'name' => 'Local Test',
                'email' => "$role@example.test",
                'password' => 'Temporary test password',
                'password_confirmation' => 'Temporary test password',
                'locale' => 'ar',
                'role' => $role,
                'phone' => '٠٦ ١٢ ٣٤ ٥٦ ٧٨',
                'whatsapp_contact' => true,
            ]);
            if ($role === 'administrator') {
                $response->assertUnprocessable()->assertJsonValidationErrors('role');
                $this->assertDatabaseMissing('users', ['email' => 'administrator@example.test']);

                continue;
            }
            $response->assertCreated()->assertJsonPath('data.roles', [$role])->assertJsonPath('data.email_verified', false)
                ->assertJsonPath('data.phone', '+212612345678')->assertJsonPath('data.whatsapp_contact', true);
            Notification::assertSentTo(User::where('email', "$role@example.test")->firstOrFail(), VerifyEmail::class);
        }
    }

    public function test_verified_status_requires_a_valid_signed_link(): void
    {
        $user = $this->userWithRole('beneficiary', false);
        $this->asSession($user)->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.abilities', ['profile.update', 'applications.submit', 'applications.viewOwn', 'applications.uploadOwn']);
        $this->postJson('/api/v1/email/verification-notification')->assertNoContent();
        Notification::assertSentTo($user, VerifyEmail::class);

        $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
        $this->get(route('verification.verify', $parameters))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), $parameters, false))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(15), $parameters, false))
            ->assertRedirect(config('app.frontend_url').'/email-verified')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->asSession($user->fresh())->getJson('/api/v1/me')->assertOk()->assertJsonFragment(['applications.submit']);
    }

    public function test_profile_updates_only_allowed_fields(): void
    {
        $this->patchJson('/api/v1/me', ['name' => 'Changed'])->assertUnauthorized();
        $user = $this->userWithRole('shopkeeper');
        $this->asSession($user)->patchJson('/api/v1/me', [
            'name' => 'New name',
            'locale' => 'ar',
            'email' => 'replacement@example.test',
            'status' => 'closed',
            'role' => 'administrator',
        ])->assertOk()->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.locale', 'ar')->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['shopkeeper'])->assertJsonPath('data.status', 'active')
            ->assertJsonFragment(['shops.manageAssigned']);
    }

    public function test_local_administrator_can_access_admin_dashboard_before_verification(): void
    {
        $admin = $this->userWithRole('administrator', false);
        $this->asSession($admin);
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
        $this->app->instance('env', 'local');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_verified', false)
            ->assertJsonFragment(['users.review']);
        $this->getJson('/api/v1/admin/overview')->assertOk();
        $this->app->instance('env', 'production');
        $this->getJson('/api/v1/admin/overview')->assertForbidden();
    }

    public function test_administrator_access_requires_verified_role_and_status_changes_revoke_sessions(): void
    {
        $donor = $this->userWithRole('donor');
        $unverified = $this->userWithRole('administrator', false);
        $admin = $this->userWithRole('administrator');
        $target = '/api/v1/admin/users/'.$donor->id.'/status';
        $this->asSession($donor)->patchJson('/api/v1/me', ['phone' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->patchJson('/api/v1/me', ['phone' => '00212 612 345 678', 'whatsapp_contact' => true])->assertOk()
            ->assertJsonPath('data.phone', '+212612345678')->assertJsonPath('data.whatsapp_contact', true);
        $this->asSession($donor)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson($target, ['status' => 'suspended'])->assertForbidden();
        $this->asSession($unverified)->getJson('/api/v1/admin/users')->assertForbidden();
        $this->asSession($admin)->getJson('/api/v1/admin/users')->assertOk()
            ->assertJsonMissingPath('data.0.password')->assertJsonStructure(['meta' => ['next_cursor', 'prev_cursor']]);
        $this->getJson('/api/v1/admin/users?'.http_build_query(['role' => 'donor', 'q' => $donor->email]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $donor->id)
            ->assertJsonPath('data.0.phone', '+212612345678')->assertJsonPath('data.0.whatsapp_contact', true);
        $this->getJson('/api/v1/admin/users?'.http_build_query(['q' => '%_']))->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson($target, ['status' => 'suspended', 'current_password' => 'password'])->assertUnprocessable();
        $this->patchJson($target, ['status' => 'suspended', 'reason' => 'Account review fixture', 'current_password' => 'incorrect'])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/users/'.$unverified->id.'/status', ['status' => 'suspended', 'reason' => 'Account review fixture', 'current_password' => 'password'])->assertForbidden();
        $this->storeSession($donor);
        $this->patchJson($target, ['status' => 'suspended', 'reason' => 'Account review fixture', 'current_password' => 'password'])
            ->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertDatabaseMissing('sessions', ['user_id' => $donor->id]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'target_id' => $donor->id, 'action' => 'account.status_changed']);
        $this->getJson('/api/v1/admin/audit?target_id='.$donor->id)->assertOk()
            ->assertJsonPath('data.0.changes.from', 'active')->assertJsonPath('data.0.changes.to', 'suspended')
            ->assertJsonPath('data.0.changes.reason', 'Account review fixture')->assertJsonMissingPath('data.0.metadata');
        $this->patchJson($target, ['status' => 'active', 'reason' => 'Account review fixture', 'current_password' => 'password'])->assertOk();
        $this->asSession($donor->fresh())->withSession(['account_version' => $donor->session_version])
            ->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_reset_is_single_use_and_revokes_existing_session(): void
    {
        $user = $this->userWithRole('donor');
        $this->storeSession($user);
        $this->postJson('/forgot-password', ['email' => 'missing@example.test'])->assertNoContent();
        Notification::assertNothingSent();
        $this->postJson('/forgot-password', ['email' => $user->email])->assertNoContent();
        $token = Notification::sent($user, ResetPassword::class)->sole()->token;
        $password = 'New stronger test password';
        $body = ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password];
        $this->postJson('/reset-password', $body)->assertNoContent();
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertSame($user->session_version + 1, $user->fresh()->session_version);
        $this->postJson('/reset-password', $body)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_console_provisions_an_unverified_administrator_and_audits_it(): void
    {
        $this->artisan('accounts:create-administrator', ['email' => 'local-admin@example.test'])
            ->expectsQuestion('Administrator name', 'Local Admin')
            ->expectsQuestion('New administrator password', 'Local admin test password')
            ->expectsQuestion('Confirm administrator password', 'Local admin test password')
            ->assertSuccessful();

        $user = User::where('email', 'local-admin@example.test')->firstOrFail();
        $this->assertTrue($user->roles->contains('code', 'administrator'));
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', ['target_id' => $user->id, 'action' => 'account.admin_provisioned']);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_console_reports_an_existing_email_before_asking_for_a_password(): void
    {
        $user = $this->userWithRole('donor');
        $this->artisan('accounts:create-administrator', ['email' => $user->email])
            ->expectsOutput('This email is already registered. Use a different address for the administrator account.')
            ->assertFailed();
        $this->assertFalse($user->roles()->where('code', 'administrator')->exists());
    }

    public function test_administrator_password_confirmation_is_rate_limited(): void
    {
        $admin = $this->userWithRole('administrator');
        $donor = $this->userWithRole('donor');
        $endpoint = '/api/v1/admin/users/'.$donor->id.'/status';
        $this->asSession($admin);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->patchJson($endpoint, ['status' => 'suspended', 'reason' => 'Account review fixture', 'current_password' => 'wrong'])->assertUnprocessable();
        }

        $this->patchJson($endpoint, ['status' => 'suspended', 'reason' => 'Account review fixture', 'current_password' => 'wrong'])
            ->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
        $this->assertSame('active', $donor->fresh()->status);
    }

    public function test_database_queue_keeps_recovery_addresses_encrypted_and_delivers_the_notification(): void
    {
        config(['queue.default' => 'database']);
        $user = $this->userWithRole('donor');

        $this->postJson('/forgot-password', ['email' => $user->email])->assertNoContent();
        $payload = DB::table('jobs')->value('payload');
        $this->assertIsString($payload);
        $this->assertStringNotContainsString($user->email, $payload);
        Notification::assertNothingSent();

        $this->artisan('queue:work', ['--once' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    private function userWithRole(string $code, bool $verified = true): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
        $user->roles()->attach(Role::where('code', $code)->firstOrFail()->id, ['granted_at' => now()]);

        return $user->refresh();
    }

    private function asSession(User $user): self
    {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web')->withSession(['account_version' => $user->session_version]);
    }

    private function storeSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'test-session',
            'user_id' => $user->id,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
    }
}
