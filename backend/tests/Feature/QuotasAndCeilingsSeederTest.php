<?php

namespace Tests\Feature;

use Modules\JeaProjects\Engine\Disciplines;
use Modules\JeaProjects\Models\Engineer;
use Modules\JeaProjects\Models\EngineerDisciplineQuota;
use App\Models\Organization;
use Modules\JeaProjects\Models\OfficeCeiling;
use App\Models\User;
use Modules\JeaProjects\Database\Seeders\QuotasAndCeilingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JORD-67: pins the JEA Ch.9 quota + ceiling defaults seeded onto the
 * demo org and its engineers. Also proves the seeder gracefully
 * handles the 'civil' → 'structural' alias and skips engineers with
 * unknown / null specializations.
 */
class QuotasAndCeilingsSeederTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $officeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create([
            'name_ar' => 'demo', 'name_en' => 'demo', 'slug' => 'demo', 'is_active' => true,
        ]);
        $this->officeUser = User::create([
            'organization_id' => $this->org->id, 'name' => 'office', 'email' => 'office@t.esp',
            'password' => 'x', 'role' => 'applicant', 'is_active' => true, 'password_changed_at' => now(),
        ]);
    }

    public function test_seeder_creates_one_office_ceiling_per_discipline(): void
    {
        $this->runSilently(new QuotasAndCeilingsSeeder());

        $year = (int) now()->year;
        foreach (Disciplines::all() as $d) {
            $ceiling = OfficeCeiling::where('organization_id', $this->org->id)
                ->where('discipline', $d)->where('year', $year)->first();
            $this->assertNotNull($ceiling, "Missing office ceiling for {$d}");
            $this->assertGreaterThan(0, $ceiling->m2_allowed);
        }
    }

    public function test_seeder_creates_per_engineer_quotas_for_declared_discipline_only(): void
    {
        // Each seeded engineer only gets a quota row for the discipline
        // they're registered under — not all 5. This mirrors the JEA
        // model where an engineer's classification tie is 1-to-1.
        $engineer = $this->makeEngineer('EN-001', Disciplines::ARCHITECTURAL);
        $this->runSilently(new QuotasAndCeilingsSeeder());

        $rows = EngineerDisciplineQuota::where('engineer_id', $engineer->id)->get();
        $this->assertCount(1, $rows, 'Engineer should have exactly one quota row (their discipline)');
        $this->assertSame(Disciplines::ARCHITECTURAL, $rows->first()->discipline);
        $this->assertSame(56250, $rows->first()->m2_allowed,
            'Architectural default per JEA Ch.9 = 56,250 m²/yr');
    }

    public function test_civil_alias_folds_to_structural(): void
    {
        // Legacy seed uses 'civil'; the alias in Disciplines::normalize
        // must land the quota row under 'structural'.
        $engineer = $this->makeEngineer('EN-CIVIL', 'civil');
        $this->runSilently(new QuotasAndCeilingsSeeder());

        $row = EngineerDisciplineQuota::where('engineer_id', $engineer->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(Disciplines::STRUCTURAL, $row->discipline,
            'civil must fold to structural (Disciplines::normalize)');
    }

    public function test_engineer_with_unknown_discipline_is_skipped_not_defaulted(): void
    {
        // An engineer whose specialization doesn't match ANY canonical
        // discipline or alias must NOT get a silent default row —
        // that would produce phantom quota nobody at JEA authorized.
        $engineer = $this->makeEngineer('EN-BOGUS', 'roadway_transport');
        $this->runSilently(new QuotasAndCeilingsSeeder());

        $this->assertSame(0,
            EngineerDisciplineQuota::where('engineer_id', $engineer->id)->count(),
            'Unknown discipline must NOT auto-create a quota');
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->makeEngineer('EN-001', Disciplines::STRUCTURAL);
        $this->runSilently(new QuotasAndCeilingsSeeder());
        $this->runSilently(new QuotasAndCeilingsSeeder());

        // Composite unique (engineer_id, discipline, year) enforces
        // this at the DB level too, but pin the behavior at the app level
        // so a future removal of the constraint still gets caught here.
        $this->assertSame(1,
            EngineerDisciplineQuota::where('engineer_id', Engineer::first()->id)->count(),
            'Re-running the seeder must not duplicate rows');
        $this->assertSame(count(Disciplines::all()),
            OfficeCeiling::where('organization_id', $this->org->id)->count(),
            'Office ceilings must stay at one-per-discipline after re-runs');
    }

    public function test_environmental_engineer_gets_the_higher_118750_quota(): void
    {
        // Per manual p.124 environmental engineers have a higher cap
        // (118,750 vs 56,250 for the other 4 disciplines).
        $engineer = $this->makeEngineer('EN-ENV', Disciplines::ENVIRONMENTAL);
        $this->runSilently(new QuotasAndCeilingsSeeder());
        $row = EngineerDisciplineQuota::where('engineer_id', $engineer->id)->first();
        $this->assertSame(118750, $row->m2_allowed);
    }

    private function makeEngineer(string $membership, string $specialization): Engineer
    {
        return Engineer::create([
            'organization_id'   => $this->org->id,
            'office_user_id'    => $this->officeUser->id,
            'name_ar'           => 'م',
            'membership_number' => $membership,
            'specialization'    => $specialization,
        ]);
    }

    private function runSilently(\Illuminate\Database\Seeder $seeder): void
    {
        $seeder->setContainer($this->app)
            ->setCommand(new class extends \Illuminate\Console\Command {
                public function info($string, $verbosity = null): void {}
                public function error($string, $verbosity = null): void {}
                public function warn($string, $verbosity = null): void {}
            })
            ->run();
    }
}
