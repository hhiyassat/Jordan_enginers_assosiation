<?php

namespace Plugins\Captcha\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * CaptchaService
 *
 * Simple text-captcha implementation without external providers.
 * Generates a 6-character alphanumeric challenge, stores the answer in
 * cache keyed by a UUID, and renders the challenge as an SVG image.
 *
 * Design notes:
 *  - Character set excludes visually ambiguous glyphs (0/O, 1/I/L).
 *  - Single-use: verify() deletes the cache entry on successful match
 *    so a captured answer cannot be replayed.
 *  - TTL default 5 minutes (configurable via config('esp.captcha_ttl_minutes')).
 *  - SVG rendering: per-character x-offset, rotation, and color variance +
 *    random noise lines. Not OCR-proof — good enough for basic bot
 *    deterrence; pair with rate limiting (SEC-009).
 */
class CaptchaService
{
    private const CHARSET   = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LENGTH    = 6;
    private const CACHE_KEY = 'captcha:';

    /**
     * @return array{id: string, svg: string}
     */
    public function generate(): array
    {
        $code = $this->randomCode();
        $id   = (string) Str::uuid();

        Cache::put(self::CACHE_KEY . $id, mb_strtoupper($code), now()->addMinutes($this->ttlMinutes()));

        return [
            'id'  => $id,
            'svg' => $this->renderSvg($code),
        ];
    }

    /**
     * Compare user's answer with the cached code. Case-insensitive.
     * Returns true and invalidates the cache entry on match.
     */
    public function verify(?string $id, ?string $answer): bool
    {
        if (!$id || !$answer) return false;

        $key = self::CACHE_KEY . $id;
        $expected = Cache::get($key);
        if ($expected === null) return false;

        $ok = hash_equals($expected, mb_strtoupper(trim($answer)));

        // Single-use: always drop the challenge after any verify attempt so
        // a wrong-then-right retry against the same captcha still requires
        // a fresh challenge — reduces replay + brute-force surface.
        Cache::forget($key);

        return $ok;
    }

    private function randomCode(): string
    {
        $len   = strlen(self::CHARSET);
        $out   = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::CHARSET[random_int(0, $len - 1)];
        }
        return $out;
    }

    private function ttlMinutes(): int
    {
        return (int) config('esp.captcha_ttl_minutes', 5);
    }

    private function renderSvg(string $code): string
    {
        $width  = 180;
        $height = 60;
        $chars  = str_split($code);

        // Character glyphs
        $glyphs = '';
        $spacing = $width / (self::LENGTH + 1);
        foreach ($chars as $i => $ch) {
            $x     = (int) round($spacing * ($i + 1));
            $y     = 40 + random_int(-4, 4);
            $rot   = random_int(-20, 20);
            $hue   = random_int(190, 230); // JEA-ish blue range
            $color = "hsl({$hue}, 60%, 30%)";
            $font  = 26 + random_int(-2, 4);
            $glyphs .= sprintf(
                '<text x="%d" y="%d" font-family="Cairo, Arial, sans-serif" font-size="%d" font-weight="900" fill="%s" transform="rotate(%d %d %d)">%s</text>',
                $x, $y, $font, $color, $rot, $x, $y, htmlspecialchars($ch, ENT_XML1)
            );
        }

        // Random noise strokes for mild OCR resistance
        $noise = '';
        for ($i = 0; $i < 6; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $noise .= sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="rgba(26,119,188,0.25)" stroke-width="1" />',
                $x1, $y1, $x2, $y2
            );
        }
        for ($i = 0; $i < 20; $i++) {
            $noise .= sprintf(
                '<circle cx="%d" cy="%d" r="%s" fill="rgba(26,119,188,0.2)" />',
                random_int(0, $width), random_int(0, $height), random_int(1, 2)
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="captcha">'
            . '<rect width="100%%" height="100%%" fill="#EEF7FC" />'
            . '%s%s'
            . '</svg>',
            $width, $height, $width, $height, $noise, $glyphs
        );
    }
}
