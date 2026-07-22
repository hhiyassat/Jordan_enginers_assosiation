<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\JeaProjects\Database\Seeders\DemoEngineersSeeder;
use Modules\JeaProjects\Database\Seeders\GovernmentSurveyQuotaSeeder;
use Modules\JeaProjects\Database\Seeders\MaterialsTestingQuotaSeeder;
use Modules\JeaProjects\Database\Seeders\QuotasAndCeilingsSeeder;
use Modules\JeaProjects\Database\Seeders\SampleProjectsSeeder;
use Modules\JeaServices\Database\Seeders\CatalogWorkflowsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingEngineerPickerSeeder;
use Modules\JeaServices\Database\Seeders\DrawingFeeMatrixSeeder;
use Modules\JeaServices\Database\Seeders\DrawingsDocumentsSeeder;
use Modules\JeaServices\Database\Seeders\DrawingValiditySeeder;
use Modules\JeaServices\Database\Seeders\ExcavationFeeSeeder;
use Modules\JeaServices\Database\Seeders\FeeSurchargesSeeder;
use Modules\JeaServices\Database\Seeders\JeaPortalTilesSeeder;
use Modules\JeaServices\Database\Seeders\JeaServicesSeeder;
use Modules\JeaServices\Database\Seeders\MaterialsSampleRetentionSeeder;
use Modules\JeaServices\Database\Seeders\ServiceFeeDefaultsSeeder;
use Modules\JeaServices\Database\Seeders\ServicePlan2026Seeder;
use Modules\JeaServices\Database\Seeders\SiteSurveyFeesSeeder;
use Modules\JeaServices\Database\Seeders\SolarFeeSeeder;
use Modules\JeaServices\Database\Seeders\SurveyWorkflowsSeeder;

/**
 * DatabaseSeeder — platform composition root.
 *
 * Workstream 12 moved the per-domain seeders out into their owning
 * modules (Modules\JeaServices\Database\Seeders\* + Modules\JeaProjects\
 * Database\Seeders\*). This class stays as the SINGLE ORDERED entry
 * point Laravel's db:seed hits, because the ordering matters (see
 * inline comments) and Laravel doesn't natively compose seeder chains
 * across modules.
 *
 * When a jea-* module is disabled at runtime (config/modules.enabled),
 * its seeder still autoloads via composer but does nothing useful —
 * the module's tables don't exist, so the seeder writes nothing. Full
 * separation would require iterating enabled modules and calling each
 * module's own seeder chain; deferred to a later workstream (part of
 * the enforcement promotion in W15 or a follow-up).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ordering matters: tiles first, then the plan populates children
        // under those tiles, then catalog + survey workflows attach real
        // workflows onto the children. Sample projects / engineers depend
        // on the ahmed@demo.esp user created by DemoSeeder.
        $this->call([
            DemoSeeder::class,
            JeaServicesSeeder::class,
            JeaPortalTilesSeeder::class,
            ServicePlan2026Seeder::class,
            CatalogWorkflowsSeeder::class,
            SurveyWorkflowsSeeder::class,
            // JORD-54: shared 15-document manifest for every DRW-P-* row.
            // Runs after CatalogWorkflowsSeeder because that seeder only
            // writes schema['workflow']; documents live on their own key.
            DrawingsDocumentsSeeder::class,
            // JORD-58: 5-year approval validity on every DRW-P-* per the
            // JEA 2025 manual p. 26. Writes only schema.certificate.
            // validity_months so it's safe alongside earlier seeders.
            DrawingValiditySeeder::class,
            // JORD-60: 10-day materials-samples retention notice on
            // SRV-008/009 per JEA 2025 manual p. 36. Writes only
            // schema.compliance_notes[] — a new opt-in schema field.
            MaterialsSampleRetentionSeeder::class,
            // JORD-63: JEA 2025 fee matrix (p. 92) on every DRW-P-*.
            // Adds 3 required form fields (governorate, building_class,
            // area_m2) and replaces the fee block with a matrix lookup.
            DrawingFeeMatrixSeeder::class,
            // JORD-64: solar per-kW fee (JEA p. 71-72) on DRW-P-006.
            // Runs after the matrix seeder — overrides its fee block
            // for the one drawing service whose fee scales with
            // capacity, not area.
            SolarFeeSeeder::class,
            // JORD-64: excavation shoring per-m² fee (JEA p. 40) on
            // SRV-007/012. Independent of drawings — different services.
            ExcavationFeeSeeder::class,
            // JORD-65: 1% syndicate surcharge + 40 fils/m² drawing review
            // (JEA p. 96). Runs last so it can attach to any fee block
            // the earlier seeders set (matrix on DRW-P-*, per_unit on
            // DRW-P-006 + SRV-007/012).
            FeeSurchargesSeeder::class,
            // JORD-67: engineer quotas + office ceilings (JEA Ch. 9).
            // Data-model foundation; consumption + enforcement land in
            // JORD-68/69. Depends on Engineers seeded upstream.
            QuotasAndCeilingsSeeder::class,
            // JORD-69: adds `engineer_id` select field to every DRW-P-*
            // so the applicant picks the responsible engineer. Frontend
            // fetches options from /engineers at render time.
            DrawingEngineerPickerSeeder::class,
            // JORD-74: materials-testing per-lab quota (JEA p.125).
            // Seeds the materials_testing office ceiling + wires
            // SRV-008/009 into the quota-tracked path via
            // schema.quota_discipline_override.
            MaterialsTestingQuotaSeeder::class,
            // JORD-75: government-bidder site-survey linear-meter
            // quota (JEA p.125). SRV-006 with length_lm basis.
            GovernmentSurveyQuotaSeeder::class,
            // JORD-78: site-survey base fees (150 fils/lm + 1% syndicate)
            // per JEA p.96. Wires SRV-001..006 with real per-lm pricing.
            SiteSurveyFeesSeeder::class,
            // JORD-85: admin-editable fee defaults for every service
            // whose fee block is still the placeholder `fixed 0` — sets
            // 50000 JOD until the F-07 amounts are wired per-service
            // through the admin fee editor.
            ServiceFeeDefaultsSeeder::class,
            // JeaDrawingsSeeder omitted — its 7 DRW-* rows duplicate the
            // richer DRW-P-* set produced by ServicePlan2026Seeder + the
            // real workflows attached by CatalogWorkflowsSeeder.
            SampleProjectsSeeder::class,
            DemoEngineersSeeder::class,
        ]);
    }
}
