<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\PasswordHistory;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * P1-08 · Password policy uplift.
 *
 * The API-visible invariants:
 *   - min 12 characters (was 8)
 *   - mixedCase + numbers + symbols
 *   - reject reuse of any of the last N stored hashes for the user
 *   - HIBP check gated behind `esp.password_check_compromised`
 *     (default off for local + CI; ProductionSafety enforces it in
 *     production)
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_rule_rejects_under_12_characters(): void
    {
        $v = Validator::make(['password' => 'Aa1!Aa1!'], ['password' => [PasswordPolicy::baseRule()]]);
        $this->assertTrue($v->fails());
    }

    public function test_base_rule_rejects_missing_symbols(): void
    {
        $v = Validator::make(['password' => 'Aa123456789Bcd'], ['password' => [PasswordPolicy::baseRule()]]);
        $this->assertTrue($v->fails());
    }

    public function test_base_rule_accepts_12char_mixed_symbol_password(): void
    {
        $v = Validator::make(['password' => 'Aa123456!Bcd'], ['password' => [PasswordPolicy::baseRule()]]);
        $this->assertFalse($v->fails(), 'valid password must pass base rule');
    }

    public function test_history_rejects_reuse_of_last_five(): void
    {
        $org = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'pw-o', 'is_active' => true]);
        $user = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'pw@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        // Seed history with the current password.
        PasswordHistory::remember($user, Hash::make('Aa123456!Bcd'));

        $v = Validator::make(
            ['password' => 'Aa123456!Bcd'],
            ['password' => [PasswordPolicy::baseRule(), PasswordPolicy::distinctFromHistoryFor($user)]]
        );
        $this->assertTrue($v->fails(), 'password reuse must be rejected by history rule');
    }

    public function test_history_prunes_to_configured_window(): void
    {
        $org = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'pw-o2', 'is_active' => true]);
        $user = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'pw2@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        // Push 7 hashes; window default 5.
        for ($i = 1; $i <= 7; $i++) {
            PasswordHistory::remember($user, Hash::make("Aa123456!Bcd{$i}"));
        }
        $this->assertCount(5, PasswordHistory::recentHashesFor($user),
            'history must be trimmed to esp.password_history_size (default 5)');
    }

    public function test_change_password_records_new_hash_to_history(): void
    {
        $org = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'pw-o3', 'is_active' => true]);
        $user = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'pw3@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'Aa123456!Bcd',
            'password'              => 'NewPass1234!Bc',
            'password_confirmation' => 'NewPass1234!Bc',
        ])->assertOk();

        $hashes = PasswordHistory::recentHashesFor($user->fresh());
        $this->assertNotEmpty($hashes, 'history must contain at least one entry after change');
        $this->assertTrue(Hash::check('NewPass1234!Bc', $hashes[0]));
    }

    public function test_change_password_rejects_reuse_of_current_password(): void
    {
        $org = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'pw-o4', 'is_active' => true]);
        $user = User::create([
            'organization_id' => $org->id, 'name' => 'a', 'email' => 'pw4@t.esp',
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        // Seed history so the "reuse of current" is caught.
        PasswordHistory::remember($user, Hash::make('Aa123456!Bcd'));
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/password/change', [
            'current_password'      => 'Aa123456!Bcd',
            'password'              => 'Aa123456!Bcd',
            'password_confirmation' => 'Aa123456!Bcd',
        ])->assertStatus(422);
    }
}
