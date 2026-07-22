<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/**
 * `php artisan user:credentials {email} {--new-email=} {--password=}`
 *
 * The ONLY legitimate way to rotate a superuser's login credentials once
 * the first-login gate has closed. UserManagementController + AuthController
 * both refuse superuser password/email changes via the API — a stolen token
 * therefore can't swap the recovery email or reset the password. This
 * command bypasses those gates because it's only reachable by someone with
 * shell access to the server.
 *
 * The command also handles non-superuser resets (useful when a locked-out
 * admin needs credentials pushed in from the terminal). It never removes
 * a user, never elevates a role, and always leaves the target on the
 * must_change_password path so the operator picks their own final password.
 */
class UserCredentials extends Command
{
    protected $signature = 'user:credentials
                            {email : Current login email of the user to rotate}
                            {--new-email= : New login email (optional)}
                            {--password= : New password (prompts if omitted)}';

    protected $description = "Rotate a superuser's login credentials from the CLI (only legitimate path post-init).";

    public function handle(): int
    {
        $email = $this->argument('email');

        /** @var User|null $user */
        $user = User::withTrashed()->where('email', $email)->first();
        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        if ($user->trashed()) {
            $this->error("User {$email} is soft-deleted. Restore first: php artisan tinker → User::withTrashed()->find({$user->id})->restore()");
            return self::FAILURE;
        }

        $newEmail = $this->option('new-email');
        $password = $this->option('password') ?: $this->secret('New password (min 8, mixed case + numbers)');

        // Reject the same rule the API uses so CLI-set passwords still
        // clear the login flow. secret() returns null on empty input.
        try {
            Validator::make(
                ['password' => (string) $password, 'email' => $newEmail],
                [
                    'password' => ['required', Password::min(8)->mixedCase()->numbers()],
                    'email'    => ['nullable', 'email', 'unique:users,email,' . $user->id],
                ]
            )->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $m) {
                    $this->error("{$field}: {$m}");
                }
            }
            return self::FAILURE;
        }

        $update = [
            'password'             => Hash::make($password),
            // Force the operator to pick their own final password on next
            // login — the CLI password is a bootstrap, never a keeper.
            'must_change_password' => true,
            'password_changed_at'  => null,
        ];
        if ($newEmail) {
            $update['email'] = $newEmail;
        }
        $user->update($update);

        // Existing sessions on the old credentials are no longer valid.
        $user->tokens()->delete();

        $finalEmail = $newEmail ?: $email;
        $this->info("✓ Credentials rotated for {$finalEmail}");
        $this->info('  must_change_password=true — user picks a permanent password on next login.');
        return self::SUCCESS;
    }
}
