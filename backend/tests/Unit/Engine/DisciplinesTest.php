<?php

declare(strict_types=1);

namespace Tests\Unit\Engine;

use Modules\JeaProjects\Engine\Disciplines;
use PHPUnit\Framework\TestCase;

/**
 * JORD-67: pins the canonical 5-discipline set + the legacy 'civil'
 * alias. Every quota / consumption / ceiling boundary calls
 * Disciplines::normalize() first — so the alias mapping is a
 * load-bearing invariant.
 */
class DisciplinesTest extends TestCase
{
    public function test_all_returns_the_five_canonical_disciplines(): void
    {
        $this->assertSame(
            ['architectural', 'structural', 'electrical', 'mechanical', 'environmental'],
            Disciplines::all(),
            'JEA Ch.9 defines exactly these 5 disciplines'
        );
    }

    public function test_normalize_maps_civil_to_structural(): void
    {
        $this->assertSame('structural', Disciplines::normalize('civil'),
            'Legacy engineer seed uses civil; every downstream quota query must fold to structural');
    }

    public function test_normalize_is_a_noop_for_canonical_values(): void
    {
        foreach (Disciplines::all() as $d) {
            $this->assertSame($d, Disciplines::normalize($d),
                'normalize({$d}) must be idempotent for canonical values');
        }
    }

    public function test_normalize_returns_unknown_values_unchanged(): void
    {
        // A typo like 'archtctrl' must NOT silently map to a known
        // discipline — better to surface as "no quota found" later.
        $this->assertSame('archtctrl', Disciplines::normalize('archtctrl'));
    }

    public function test_labels_carry_bilingual_names_for_all_disciplines(): void
    {
        $labels = Disciplines::labels();
        foreach (Disciplines::all() as $d) {
            $this->assertArrayHasKey($d, $labels);
            $this->assertArrayHasKey('ar', $labels[$d]);
            $this->assertArrayHasKey('en', $labels[$d]);
            $this->assertNotEmpty($labels[$d]['ar']);
            $this->assertNotEmpty($labels[$d]['en']);
        }
    }
}
