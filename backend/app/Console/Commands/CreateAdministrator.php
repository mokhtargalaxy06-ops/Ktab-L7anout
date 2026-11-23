<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountSecurity;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
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
        $emailValidator = Validator::make(['email' => $email], ['email' => 'bail|required|email|max:254|unique:users,email'], [
            'email.unique' => 'This email is already registered. Use a different address for the administrator account.',
        ]);
        if ($emailValidator->fails()) {
            $this->error($emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        $name = $this->ask('Administrator name');
        $password = $this->secret('New administrator password', false);
        $confirmation = $this->secret('Confirm administrator password', false);
        $validator = Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => 'required|string|max:120', 'password' => 'bail|required|string|min:12|confirmed'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        if (strlen($password) > 72 || str_contains($password, "\0")) {
            $this->error('The password must be at most 72 bytes and cannot contain null bytes.');

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($security, $name, $email, $password) {
                $user = User::create(compact('name', 'email', 'password'));
                $role = Role::where('code', 'administrator')->firstOrFail();
                $user->roles()->attach($role->id, ['granted_at' => now()]);
                $security->audit(null, $user, 'account.admin_provisioned', []);

                return $user->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! User::where('email', $email)->exists()) {
                throw $exception;
            }
            $this->error('This email is already registered. Use a different address for the administrator account.');

            return self::FAILURE;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface) {
            $this->warn('Account created. Sign in and request another verification email after restoring mail delivery.');
        }
        $this->info(app()->environment('local')
            ? 'Administrator created. You can sign in to the local administrator dashboard now.'
            : 'Administrator created. Verify the email address before using administrative functions.');

        return self::SUCCESS;
    }
}
