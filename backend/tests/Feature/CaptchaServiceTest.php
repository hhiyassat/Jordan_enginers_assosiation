<?php

namespace Tests\Feature;

use Plugins\Captcha\Services\CaptchaService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * CaptchaService unit-level tests. Uses the array cache driver via the
 * testing env in phpunit.xml — no real Redis/DB required.
 */
class CaptchaServiceTest extends TestCase
{
    private CaptchaService $captcha;

    protected function setUp(): void
    {
        parent::setUp();
        $this->captcha = new CaptchaService();
    }

    public function test_generate_returns_uuid_id_and_svg_string(): void
    {
        $result = $this->captcha->generate();

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('svg', $result);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $result['id']
        );
        $this->assertStringStartsWith('<svg', $result['svg']);
        $this->assertStringContainsString('</svg>', $result['svg']);
    }

    public function test_verify_succeeds_with_correct_uppercase_answer(): void
    {
        $this->captcha->generate();
        // Read the last cache write directly so we know what was stored.
        // The prefix 'captcha:' + UUID.
        $id = null;
        $answer = null;
        foreach ($this->allCacheKeys() as $key) {
            if (str_starts_with($key, 'captcha:')) {
                $id     = substr($key, 8);
                $answer = Cache::get($key);
                break;
            }
        }
        $this->assertNotNull($id);
        $this->assertNotNull($answer);

        $this->assertTrue($this->captcha->verify($id, $answer));
    }

    public function test_verify_is_case_insensitive(): void
    {
        $this->captcha->generate();
        [$id, $answer] = $this->firstCaptchaEntry();

        $this->assertTrue($this->captcha->verify($id, strtolower($answer)));
    }

    public function test_verify_trims_whitespace(): void
    {
        $this->captcha->generate();
        [$id, $answer] = $this->firstCaptchaEntry();

        $this->assertTrue($this->captcha->verify($id, "  {$answer}  "));
    }

    public function test_verify_is_single_use_success_path(): void
    {
        $this->captcha->generate();
        [$id, $answer] = $this->firstCaptchaEntry();

        $this->assertTrue($this->captcha->verify($id, $answer));
        // Second attempt with the same id+answer must fail because the
        // cache entry was consumed on the first verify.
        $this->assertFalse($this->captcha->verify($id, $answer));
    }

    public function test_verify_wrong_answer_still_consumes_the_challenge(): void
    {
        $this->captcha->generate();
        [$id, $answer] = $this->firstCaptchaEntry();

        $this->assertFalse($this->captcha->verify($id, 'WRONG1'));
        // Retry with the correct answer must also fail — challenge is gone.
        $this->assertFalse($this->captcha->verify($id, $answer));
    }

    public function test_verify_rejects_null_and_empty(): void
    {
        $this->captcha->generate();
        [$id]  = $this->firstCaptchaEntry();

        $this->assertFalse($this->captcha->verify(null, 'ABC123'));
        $this->assertFalse($this->captcha->verify($id, null));
        $this->assertFalse($this->captcha->verify($id, ''));
        $this->assertFalse($this->captcha->verify('', 'ABC123'));
    }

    public function test_verify_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->captcha->verify('00000000-0000-0000-0000-000000000000', 'ABC123'));
    }

    public function test_generated_code_only_uses_unambiguous_charset(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->captcha->generate();
            [, $answer] = $this->firstCaptchaEntry();
            $this->assertMatchesRegularExpression(
                '/^[2-9A-HJ-NP-Z]{6}$/',
                $answer,
                "Charset should exclude 0/O/1/I/L; got: {$answer}"
            );
            Cache::flush();
        }
    }

    /** @return array{0: string, 1: string} */
    private function firstCaptchaEntry(): array
    {
        foreach ($this->allCacheKeys() as $key) {
            if (str_starts_with($key, 'captcha:')) {
                return [substr($key, 8), (string) Cache::get($key)];
            }
        }
        $this->fail('No captcha entry found in cache');
    }

    /** @return list<string> */
    private function allCacheKeys(): array
    {
        // The array cache repo doesn't expose keys; introspect via reflection
        // (only used for tests). Falls back to empty if driver isn't 'array'.
        $store = Cache::getStore();
        if (!method_exists($store, 'getStorage')) {
            $ref = new \ReflectionClass($store);
            if (!$ref->hasProperty('storage')) return [];
            $prop = $ref->getProperty('storage');
            $prop->setAccessible(true);
            $storage = $prop->getValue($store);
        } else {
            $storage = $store->getStorage();
        }
        return is_array($storage) ? array_keys($storage) : [];
    }
}
