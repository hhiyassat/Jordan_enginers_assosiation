<?php

declare(strict_types=1);

namespace Modules\JeaServices\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\JeaServices\Engine\ExplorationRequirementMatrix;
use Modules\JeaServices\Engine\NetDepthTable;
use Modules\JeaServices\Engine\WellsCountCalculator;
use Modules\JeaServices\Models\RuleDefinition;
use Modules\JeaServices\Models\RuleVersion;

/**
 * SG-04 · seeds the three SRV-001 rule definitions + a legacy version each.
 *
 * The rule identifiers and status classifications come from JDG-SG04-01
 * and mirror the file-header disclaimers in the existing calculator classes.
 * WellsCountCalculator and NetDepthTable cite meeting minutes; they are
 * PROVISIONAL. ExplorationRequirementMatrix cites the JEA-signed 2025
 * manual; it is APPROVED at the source-reference level but the SRV-001
 * runtime wiring around it remains pilot-only — the rule status here
 * reflects the calculator's own provenance only.
 */
class Srv001RulesSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = RuleDefinition::updateOrCreate(
            ['rule_identifier' => 'SRV001_EXPLORATION_MATRIX'],
            [
                'display_name' => 'SRV-001 exploration requirement matrix',
                'description'  => 'Row-per-floor-band × area-band table that yields point count + total depth. Source: كتاب التعليمات الفنية 2025 ص 230-231.',
            ],
        );
        RuleVersion::updateOrCreate(
            ['rule_definition_id' => $matrix->id, 'version_identifier' => 'legacy-pilot-2026-08-01'],
            [
                'implementation_identity'  => ExplorationRequirementMatrix::class,
                'source_reference'         => 'كتاب التعليمات الفنية 2025 ص 230-231 (JEA-signed technical reference)',
                'business_approval_status' => RuleVersion::STATUS_APPROVED,
                'effective_from'           => now(),
            ],
        );

        $wells = RuleDefinition::updateOrCreate(
            ['rule_identifier' => 'SRV001_WELLS_COUNT'],
            [
                'display_name' => 'SRV-001 wells count band calculator',
                'description'  => 'Wells-count derivation as a function of floor area. PROVISIONAL — sourced from meeting minutes not JEA-signed technical reference.',
            ],
        );
        RuleVersion::updateOrCreate(
            ['rule_definition_id' => $wells->id, 'version_identifier' => 'legacy-pilot-2026-08-01'],
            [
                'implementation_identity'  => WellsCountCalculator::class,
                'source_reference'         => 'محضر اجتماع 2026-07-26 §X (PROVISIONAL — not JEA-signed)',
                'business_approval_status' => RuleVersion::STATUS_PROVISIONAL,
                'effective_from'           => now(),
            ],
        );

        $netDepth = RuleDefinition::updateOrCreate(
            ['rule_identifier' => 'SRV001_NET_DEPTH'],
            [
                'display_name' => 'SRV-001 net depth table (third / two_thirds / total)',
                'description'  => 'Per-floor-count depth decomposition. PROVISIONAL — sourced from meeting minutes; acknowledged unresolved invariant third + two_thirds ≠ total.',
            ],
        );
        RuleVersion::updateOrCreate(
            ['rule_definition_id' => $netDepth->id, 'version_identifier' => 'legacy-pilot-2026-08-01'],
            [
                'implementation_identity'  => NetDepthTable::class,
                'source_reference'         => 'محضر اجتماع 2026-07-26 §XI (PROVISIONAL — third + two_thirds ≠ total unresolved)',
                'business_approval_status' => RuleVersion::STATUS_PROVISIONAL,
                'effective_from'           => now(),
            ],
        );
    }
}
