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
            // JeaDrawingsSeeder omitted — its 7 DRW-* rows duplicate the
            // richer DRW-P-* set produced by ServicePlan2026Seeder + the
            // real workflows attached by CatalogWorkflowsSeeder.
            SampleProjectsSeeder::class,
            DemoEngineersSeeder::class,
        ]);
    }
}
