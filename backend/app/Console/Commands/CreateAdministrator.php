<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountSecurity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CreateAdministrator extends Command
{
    protected $signature = 'accounts:create-administrator {email}';

    protected $description = 'Provision a new administrator from a trusted server console';

    public function handle(AccountSecurity $security): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $name = $this->ask('Administrator name');
        $password = $this->secret('New administrator password', false);
        $confirmation = $this->secret('Confirm administrator password', false);
        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => 'required|string|max:120', 'email' => 'required|email|max:254|unique:users,email', 'password' => 'required|string|min:12|confirmed'],
        );

        if ($validator->fails() || strlen($password ?? '') > 72 || str_contains($password ?? '', "\0")) {
            $this->error('Account details are invalid or the email is already in use.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($security, $name, $email, $password) {
            $user = User::create(compact('name', 'email', 'password'));
            $role = Role::where('code', 'administrator')->firstOrFail();
            $user->roles()->attach($role->id, ['granted_at' => now()]);
            $security->audit(null, $user, 'account.admin_provisioned', []);

            return $user->refresh();
        });

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface) {
            $this->warn('Account created. Sign in and request another verification email after restoring mail delivery.');
        }
        $this->info('Administrator created. Verify the email address before using administrative functions.');

        return self::SUCCESS;
    }
}
