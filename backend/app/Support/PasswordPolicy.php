<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * PasswordPolicy — single source of truth for password rules.
 *
 * P1-08 remediation. Bumps minimum length from 8 to 12, adds
 * required symbols, and — when `esp.password_check_compromised`
 * is true — consults the HIBP k-anonymity API to reject known-
 * leaked passwords. Tests run with the HIBP check disabled by
 * default (network flakiness would poison the suite); production
 * boot enforces it via ProductionSafety.
 *
 * Also provides `distinctFromHistoryFor($user)` — a scoped rule
 * that rejects any password matching the last five hashes for
 * this user. History is populated in `PasswordHistory::remember()`
 * on every successful password change.
 */
final class PasswordPolicy
{
    /**
     * The base password rule set. Used by every password-accepting
     * endpoint: register, change, admin create, admin update, CLI
     * user:credentials.
     */
    public static function baseRule(): Password
    {
        $rule = Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols();

        if ((bool) config('esp.password_check_compromised', false)) {
            $rule = $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * Returns a rule that additionally rejects any password matching
     * the caller's last five stored hashes. Callers pass either a
     * User or null (during registration when no prior user exists).
     */
    public static function distinctFromHistoryFor(?User $user): ValidationRule
    {
        return new class ($user) implements ValidationRule {
            public function __construct(private readonly ?User $user) {}
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if ($this->user === null || !is_string($value)) return;
                foreach (PasswordHistory::recentHashesFor($this->user) as $oldHash) {
                    if (Hash::check($value, $oldHash)) {
                        $fail('لا يمكن إعادة استخدام كلمة مرور سابقة (الخمس الأخيرة).');
                        return;
                    }
                }
            }
        };
    }
}
