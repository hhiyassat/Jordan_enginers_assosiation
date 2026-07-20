<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

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
            // JeaDrawingsSeeder omitted — its 7 DRW-* rows duplicate the
            // richer DRW-P-* set produced by ServicePlan2026Seeder + the
            // real workflows attached by CatalogWorkflowsSeeder.
            SampleProjectsSeeder::class,
            DemoEngineersSeeder::class,
        ]);
    }
}
