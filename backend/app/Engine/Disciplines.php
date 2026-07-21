<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Disciplines — canonical list of engineering disciplines used by the
 * quota / ceiling / capacity system (JORD-67 onwards).
 *
 * Sourced from JEA 2025 technical-instructions manual (Ch. 9, pp. 124-129).
 *
 * Note on 'civil': the older Engineer seed uses "civil" as a discipline
 * code, but the manual and every quota table in Chapter 9 says
 * "structural" (إنشائي / هيكلي). Rather than force a breaking rename,
 * normalize() maps 'civil' → 'structural' at every consumption /
 * quota-lookup boundary. New engineers should be assigned STRUCTURAL
 * directly; the alias exists purely for existing rows and legacy
 * imports.
 */
final class Disciplines
{
    public const ARCHITECTURAL = 'architectural';
    public const STRUCTURAL    = 'structural';
    public const ELECTRICAL    = 'electrical';
    public const MECHANICAL    = 'mechanical';
    public const ENVIRONMENTAL = 'environmental';

    /** Alias — legacy engineer seed uses 'civil' for what the manual calls 'structural'. */
    public const CIVIL_ALIAS = 'civil';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ARCHITECTURAL,
            self::STRUCTURAL,
            self::ELECTRICAL,
            self::MECHANICAL,
            self::ENVIRONMENTAL,
        ];
    }

    /**
     * Fold aliases onto their canonical codes so every downstream query
     * (quota lookup, consumption insert, ceiling gate) operates on ONE
     * canonical set. Any unknown value is returned unchanged so a
     * mistyped discipline surfaces as a "no quota found" error rather
     * than a silent mapping to structural.
     */
    public static function normalize(string $code): string
    {
        return $code === self::CIVIL_ALIAS ? self::STRUCTURAL : $code;
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function labels(): array
    {
        return [
            self::ARCHITECTURAL => ['ar' => 'معماري',   'en' => 'Architectural'],
            self::STRUCTURAL    => ['ar' => 'إنشائي',   'en' => 'Structural'],
            self::ELECTRICAL    => ['ar' => 'كهربائي',  'en' => 'Electrical'],
            self::MECHANICAL    => ['ar' => 'ميكانيكي', 'en' => 'Mechanical'],
            self::ENVIRONMENTAL => ['ar' => 'بيئي',     'en' => 'Environmental'],
        ];
    }
}
