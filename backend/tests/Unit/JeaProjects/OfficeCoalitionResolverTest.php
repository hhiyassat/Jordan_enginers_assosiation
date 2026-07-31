<?php

declare(strict_types=1);

namespace Tests\Unit\JeaProjects;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\JeaProjects\Models\OfficeCoalition;
use Modules\JeaProjects\Models\OfficeCoalitionMember;
use Modules\JeaProjects\Support\OfficeCoalitionResolver;
use Tests\TestCase;

/**
 * H-08 · OfficeCoalitionResolver pins the same behavior that
 * `User::activeCoalition()` had before it was extracted, so
 * QuotaLedger (the caller) still sees the same coalition
 * semantics.
 */
class OfficeCoalitionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_user_returns_null(): void
    {
        $this->assertNull(OfficeCoalitionResolver::activeCoalitionFor(null));
    }

    public function test_standalone_office_returns_null(): void
    {
        $org  = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'coal-a', 'is_active' => true]);
        $user = $this->makeUser($org, 'coal-a@t.esp');
        $this->assertNull(OfficeCoalitionResolver::activeCoalitionFor($user));
    }

    public function test_active_membership_returns_the_coalition(): void
    {
        $org  = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'coal-b', 'is_active' => true]);
        $user = $this->makeUser($org, 'coal-b@t.esp');
        $coalition = OfficeCoalition::create([
            'name'      => 'Alliance',
            'formed_at' => now()->subDay(),
        ]);
        OfficeCoalitionMember::create([
            'office_coalition_id' => $coalition->id,
            'organization_id'     => $org->id,
            'office_user_id'      => $user->id,
            'joined_at'           => now()->subDay(),
        ]);

        $result = OfficeCoalitionResolver::activeCoalitionFor($user);
        $this->assertNotNull($result);
        $this->assertSame($coalition->id, $result->id);
    }

    public function test_left_membership_returns_null(): void
    {
        $org  = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'coal-c', 'is_active' => true]);
        $user = $this->makeUser($org, 'coal-c@t.esp');
        $coalition = OfficeCoalition::create([
            'name'      => 'Alliance',
            'formed_at' => now()->subDays(30),
        ]);
        OfficeCoalitionMember::create([
            'office_coalition_id' => $coalition->id,
            'organization_id'     => $org->id,
            'office_user_id'      => $user->id,
            'joined_at'           => now()->subDays(30),
            'left_at'             => now()->subDay(),
        ]);

        $this->assertNull(OfficeCoalitionResolver::activeCoalitionFor($user));
    }

    public function test_dissolved_coalition_returns_null(): void
    {
        $org  = Organization::create(['name_ar' => 'o', 'name_en' => 'o', 'slug' => 'coal-d', 'is_active' => true]);
        $user = $this->makeUser($org, 'coal-d@t.esp');
        $coalition = OfficeCoalition::create([
            'name'         => 'Alliance',
            'formed_at'    => now()->subDays(60),
            'dissolved_at' => now()->subDay(),
        ]);
        OfficeCoalitionMember::create([
            'office_coalition_id' => $coalition->id,
            'organization_id'     => $org->id,
            'office_user_id'      => $user->id,
            'joined_at'           => now()->subDays(60),
        ]);

        $this->assertNull(OfficeCoalitionResolver::activeCoalitionFor($user));
    }

    private function makeUser(Organization $org, string $email): User
    {
        return User::create([
            'organization_id' => $org->id, 'name' => 'office', 'email' => $email,
            'password' => Hash::make('Aa123456!Bcd'),
            'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }
}
