<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Plugins\Captcha\Services\CaptchaService;
use Tests\TestCase;

/**
 * CS-06: enforce captcha on public office-registration submit.
 *
 * The middleware short-circuits when `esp.captcha_enabled` is false —
 * standard for local dev and the rest of the suite. Every test here
 * flips it to true and drives real captcha state through the plugin's
 * cache-backed service.
 */
class OfficeRegistrationCaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['esp.captcha_enabled' => true]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'office_name_ar'    => 'مكتب اختبار CS-06 ' . uniqid(),
            'office_name_en'    => 'CS-06 Test Office ' . uniqid(),
            'office_license_no' => 'CS06-' . strtoupper(bin2hex(random_bytes(4))),
            'office_city'       => 'عمان',
            'office_address_ar' => 'شارع الاختبار — بناية 12',
            'office_phone'      => '+962-79-1234567',
            'office_email'      => 'ofr-cs06-' . uniqid() . '@example.test',
            'engineers'         => [[
                'name_ar'           => 'م. أحمد الخطيب',
                'name_en'           => 'Eng. Ahmad Khatib',
                'membership_number' => 'JEA-' . random_int(10000, 99999),
                'specialization'    => 'architectural',
            ]],
        ], $overrides);
    }

    // ── missing / invalid / expired / replayed captcha ────────────

    public function test_missing_captcha_rejected(): void
    {
        $this->postJson('/api/v1/office-registrations', $this->validPayload())
             ->assertStatus(422)
             ->assertJsonPath('captcha_failed', true);
    }

    public function test_invalid_captcha_answer_rejected(): void
    {
        $challenge = app(CaptchaService::class)->generate();
        $this->postJson('/api/v1/office-registrations', array_merge(
            $this->validPayload(),
            ['captcha_id' => $challenge['id'], 'captcha_answer' => 'WRONG-ANSWER'],
        ))->assertStatus(422)->assertJsonPath('captcha_failed', true);
    }

    public function test_expired_captcha_rejected(): void
    {
        // Manually plant a captcha id whose cache entry has already
        // expired — verify() returns false on missing entries.
        $bogusId = (string) \Illuminate\Support\Str::uuid();
        // No cache::put — the entry never existed, simulating expiry.
        $this->postJson('/api/v1/office-registrations', array_merge(
            $this->validPayload(),
            ['captcha_id' => $bogusId, 'captcha_answer' => 'ANYTHING'],
        ))->assertStatus(422)->assertJsonPath('captcha_failed', true);
    }

    public function test_replayed_captcha_rejected_after_first_use(): void
    {
        // Generate a real challenge and read the plaintext answer out of
        // cache so we can pass a *valid* first request and prove the
        // second one fails.
        $challenge = app(CaptchaService::class)->generate();
        $answer    = Cache::get('captcha:' . $challenge['id']);
        $this->assertNotNull($answer, 'CaptchaService must persist the plaintext answer under captcha:{id}');

        // First attempt with a validly-signed payload — the office_name
        // uniqueness will not clash on a fresh RefreshDatabase run, but
        // we intentionally use a payload that fails validation *after*
        // captcha (missing engineer name) so the captcha layer is the
        // only place the request can be rejected on the first call.
        //
        // Actually the simpler approach: send a well-formed payload,
        // then immediately re-send with the same captcha_id. Second
        // must be 422 with captcha_failed=true because the cache
        // entry was dropped on the first verify.
        $payload = array_merge($this->validPayload(), [
            'captcha_id'     => $challenge['id'],
            'captcha_answer' => $answer,
        ]);

        $this->postJson('/api/v1/office-registrations', $payload)->assertStatus(201);

        // Same challenge, same answer — must be rejected as replay.
        $second = $this->postJson('/api/v1/office-registrations', $payload);
        $second->assertStatus(422)->assertJsonPath('captcha_failed', true);
    }

    public function test_valid_captcha_allows_submission(): void
    {
        $challenge = app(CaptchaService::class)->generate();
        $answer    = Cache::get('captcha:' . $challenge['id']);

        $this->postJson('/api/v1/office-registrations', array_merge(
            $this->validPayload(),
            ['captcha_id' => $challenge['id'], 'captcha_answer' => $answer],
        ))->assertStatus(201);
    }

    // ── ProductionSafety hard-requires captcha ────────────────────

    public function test_production_safety_still_rejects_boot_without_captcha_enabled(): void
    {
        config(['esp.captcha_enabled' => false]);
        $violations = (new \App\Support\ProductionSafety($this->app))->collectViolations();
        $captchaViolation = false;
        foreach ($violations as $v) {
            if (str_contains($v, 'CAPTCHA_ENABLED must be true')) { $captchaViolation = true; break; }
        }
        $this->assertTrue($captchaViolation,
            'ProductionSafety must still refuse to boot production without CAPTCHA_ENABLED — CS-06 depends on it.');
    }
}
