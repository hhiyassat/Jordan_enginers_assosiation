<?php

declare(strict_types=1);

namespace Modules\JeaServices\Support;

/**
 * ArabicNormalizer — text normalization for matching Arabic strings that
 * differ only in diacritics, alef variants, or whitespace.
 *
 * Used by CadastralConflictGuard and OwnerMatchClearanceGuard so that
 * cosmetic input differences (e.g. `أحمد` vs `احمد`, `إربد` vs `اربد`,
 * `  الحوض  ` vs `الحوض`) don't defeat cross-office conflict detection.
 *
 * This is a MATCHING helper only — it does NOT alter values that get
 * stored in the database or displayed on the certificate. Values are
 * normalized on the fly for comparison purposes.
 *
 * Design assumption (documented per docs/architecture/cross-cutting-submission-pipeline.md UNQ-015):
 * Match is done on Arabic-normalized text. Digits are NOT translated
 * between Arabic-Indic (٠-٩) and Latin (0-9) yet — that would be a
 * separate policy call; add here if JEA confirms the input space
 * includes mixed digits.
 */
final class ArabicNormalizer
{
    /**
     * Arabic combining diacritics to strip:
     *   U+064B..U+0652  (tanween + fatha/damma/kasra/sukun/shadda)
     *   U+0653..U+0655  (madda/hamza/wasla marks)
     *   U+0656..U+065F  (Quranic reading marks)
     *   U+0670          (superscript alef)
     */
    private const DIACRITICS = '/[\x{064B}-\x{065F}\x{0670}]/u';

    /**
     * Alef variants that should collapse to plain alef ا (U+0627):
     *   أ U+0623   إ U+0625   آ U+0622   ٱ U+0671
     */
    private const ALEF_VARIANTS = ['أ', 'إ', 'آ', 'ٱ'];

    public static function normalize(?string $s): string
    {
        if ($s === null) {
            return '';
        }

        // 1. strip combining diacritics
        $s = (string) preg_replace(self::DIACRITICS, '', $s);

        // 2. unify alef variants → ا
        $s = str_replace(self::ALEF_VARIANTS, 'ا', $s);

        // 3. unify ya: ى (U+0649) → ي (U+064A)
        $s = str_replace('ى', 'ي', $s);

        // 4. unify hamza-on-waw/ya: ؤ (U+0624) → و ، ئ (U+0626) → ي
        $s = str_replace(['ؤ', 'ئ'], ['و', 'ي'], $s);

        // 5. unify teh marbuta: ة (U+0629) → ه (U+0647)
        //    (JEA convention treats ة/ه as interchangeable in office / owner names)
        $s = str_replace('ة', 'ه', $s);

        // 6. collapse whitespace runs to single space, then trim
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        $s = trim($s);

        // 7. lowercase Latin fragments (for mixed-script owner names)
        $s = mb_strtolower($s, 'UTF-8');

        return $s;
    }

    /**
     * Compare two Arabic strings by their normalized form.
     */
    public static function equals(?string $a, ?string $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }
}
