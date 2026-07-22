<?php

namespace Modules\JeaServices\Database\Seeders;

use App\Models\Organization;
use Modules\JeaServices\Models\ServiceDefinition;
use Illuminate\Database\Seeder;

/**
 * DrawingValiditySeeder — JORD-58
 *
 * The JEA 2025 technical-instructions manual (p. 26) pins a 5-year
 * validity on every approved engineering drawing:
 *   "تكون مدة صلاحية المخططات الهندسية خمس سنوات من تاريخ اجازتها
 *    من النقابة ... اذا رغب المالك خلال هذه المدة اعادة تصديق ...
 *    بنصف الاتعاب."
 *
 * The 10 DRW-P-* services were seeded with certificate.validity_months=0
 * (the placeholder default from ServicePlan2026Seeder::placeholderSchema).
 * This seeder patches all of them to 60 so the platform can compute an
 * expiry date on approved applications and let admins drive re-approval
 * (at half fees per the same rule).
 *
 * Idempotent: run() replaces schema.certificate.validity_months in-place,
 * so re-runs converge on 60. If an org needs a bespoke validity window
 * on one of the drawing services, they can override via the schema
 * editor after this seeder runs — the seeder is a source-of-truth
 * default, not a mid-lifecycle patcher.
 *
 * Runs after ServicePlan2026Seeder + CatalogWorkflowsSeeder because
 * both of those write to the same schema object; putting this last
 * guarantees the validity value survives the earlier writes.
 *
 * Note on the reuse of certificate.validity_months: drawing services
 * don't issue a certificate per se — they issue stamped drawings +
 * receipts (the issue_documents stage). But the field's semantic
 * ("how long is the service's approved output valid?") maps cleanly
 * to drawings too, and WorkflowEngine::issueCertificate already
 * consults it. Promoting the field to schema.output.validity_months
 * would be cleaner but touches every seeder + engine + frontend and
 * belongs in Phase 2. This seeder is the pragmatic Phase 1 delivery.
 */
class DrawingValiditySeeder extends Seeder
{
    private const DRAWING_CODES = [
        'DRW-P-001', 'DRW-P-002', 'DRW-P-003', 'DRW-P-004', 'DRW-P-005',
        'DRW-P-006', 'DRW-P-007', 'DRW-P-008', 'DRW-P-009', 'DRW-P-010',
        'DRW-P-011', 'DRW-P-012',
    ];

    private const VALIDITY_MONTHS = 60; // 5 years per manual p. 26

    public function run(): void
    {
        $org = Organization::where('slug', 'demo')->first();
        if (!$org) {
            $this->command->error('Demo organization not found. Run DemoSeeder first.');
            return;
        }

        $updated = 0;
        $missing = [];
        foreach (self::DRAWING_CODES as $code) {
            $service = ServiceDefinition::where('organization_id', $org->id)
                ->where('code', $code)
                ->first();
            if (!$service) {
                $missing[] = $code;
                continue;
            }
            $schema = $service->schema ?? [];
            // Defensive: certificate block may be missing on a bespoke
            // schema. Create it so the validity_months write always lands.
            $schema['certificate'] = $schema['certificate'] ?? [];
            $schema['certificate']['validity_months'] = self::VALIDITY_MONTHS;
            $service->update(['schema' => $schema]);
            $updated++;
        }

        $this->command->info("✓ Drawing validity set to " . self::VALIDITY_MONTHS
            . " months on {$updated} services.");
        if ($missing) {
            $this->command->warn('Missing services (skipped): ' . implode(', ', $missing));
        }
    }
}
