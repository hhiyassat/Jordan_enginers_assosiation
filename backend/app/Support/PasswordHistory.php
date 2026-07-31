<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PasswordHistory — remembers the last N password hashes per user
 * so PasswordPolicy::distinctFromHistoryFor can reject reuse.
 *
 * P1-08 remediation. N is configurable via
 * `esp.password_history_size` (default 5).
 *
 * We store hashes only (no plaintext); comparison is done in
 * memory via Hash::check when a new password is offered. The
 * table is intentionally simple — no PII, no metadata beyond a
 * timestamp for retention pruning.
 */
final class PasswordHistory
{
    private const TABLE = 'password_history';

    public static function historySize(): int
    {
        return max(1, (int) config('esp.password_history_size', 5));
    }

    /**
     * Store the given hash and trim history to the configured window.
     */
    public static function remember(User $user, string $hash): void
    {
        DB::table(self::TABLE)->insert([
            'user_id'       => $user->id,
            'password_hash' => $hash,
            'created_at'    => now(),
        ]);

        // Prune to N newest.
        $keepIds = DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::historySize())
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            DB::table(self::TABLE)
                ->where('user_id', $user->id)
                ->whereNotIn('id', $keepIds->all())
                ->delete();
        }
    }

    /**
     * Return the last N stored hashes for the user, most recent first.
     *
     * @return list<string>
     */
    public static function recentHashesFor(User $user): array
    {
        return DB::table(self::TABLE)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::historySize())
            ->pluck('password_hash')
            ->all();
    }
}
