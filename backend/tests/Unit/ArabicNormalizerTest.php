<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\JeaServices\Support\ArabicNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ArabicNormalizer — pin the normalization rules used by cross-cutting
 * guards for cadastral / owner-name matching. Any change to the normalizer
 * that would loosen or tighten matching semantics must break these tests
 * on purpose.
 */
class ArabicNormalizerTest extends TestCase
{
    #[DataProvider('equalityCases')]
    public function test_pairs_are_normalized_to_the_same_form(string $a, string $b, string $why): void
    {
        $this->assertTrue(
            ArabicNormalizer::equals($a, $b),
            "expected '{$a}' == '{$b}' after normalization ({$why})",
        );
    }

    /** @return list<array{string, string, string}> */
    public static function equalityCases(): array
    {
        return [
            'alef with hamza above'   => ['أحمد', 'احمد', 'أ → ا'],
            'alef with hamza below'   => ['إربد', 'اربد', 'إ → ا'],
            'alef with madda'         => ['آمنة', 'امنه', 'آ → ا, ة → ه'],
            'alef wasla'              => ['ٱلحمد', 'الحمد', 'ٱ → ا'],
            'alef maqsura'            => ['موسى', 'موسي', 'ى → ي'],
            'hamza on waw'            => ['مؤمن', 'مومن', 'ؤ → و'],
            'hamza on ya'             => ['قائم', 'قايم', 'ئ → ي'],
            'teh marbuta vs heh'      => ['وحدة', 'وحده', 'ة → ه'],
            'diacritics stripped'     => ['بَيْت', 'بيت', 'fatha + sukun stripped'],
            'shadda + tanween'        => ['مَدَّةٌ', 'مده', 'shadda + damma + tanween stripped, teh marbuta collapsed'],
            'whitespace runs'         => ['حي   الأمير', 'حي الامير', 'collapse multiple spaces + strip hamza'],
            'leading trailing space'  => ['  الحوض  ', 'الحوض', 'trim'],
            'mixed with latin lower'  => ['SITE Alpha', 'site alpha', 'lowercase latin'],
        ];
    }

    #[DataProvider('inequalityCases')]
    public function test_pairs_that_must_stay_different(string $a, string $b, string $why): void
    {
        $this->assertFalse(
            ArabicNormalizer::equals($a, $b),
            "expected '{$a}' != '{$b}' after normalization ({$why})",
        );
    }

    /** @return list<array{string, string, string}> */
    public static function inequalityCases(): array
    {
        return [
            'genuinely different names'    => ['أحمد', 'محمد', 'no vowel/hamza equivalence between these'],
            'digit vs word'                => ['الحوض ٧', 'الحوض سبعة', 'digit-word conversion not implemented'],
            'arabic vs latin digit'        => ['قطعة 7', 'قطعة ٧', 'digit script conversion deliberately NOT normalized (UNQ-015 open)'],
            'basin_7 vs basin_71'          => ['حوض 7', 'حوض 71', 'substring match must not be true equality'],
        ];
    }

    public function test_null_normalizes_to_empty_string(): void
    {
        $this->assertSame('', ArabicNormalizer::normalize(null));
    }

    public function test_empty_string_normalizes_to_empty_string(): void
    {
        $this->assertSame('', ArabicNormalizer::normalize(''));
        $this->assertSame('', ArabicNormalizer::normalize('   '));
    }

    public function test_normalize_is_idempotent(): void
    {
        $inputs = ['أحمد بن الخطاب', '  حي   الأمير  ', 'SITE Alpha'];
        foreach ($inputs as $s) {
            $once = ArabicNormalizer::normalize($s);
            $twice = ArabicNormalizer::normalize($once);
            $this->assertSame($once, $twice, "normalization must be idempotent for '{$s}'");
        }
    }
}
