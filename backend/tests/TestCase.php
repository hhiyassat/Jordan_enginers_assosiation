<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Root TestCase for feature tests that need the Laravel container.
 *
 * Pure-PHP unit tests (e.g., static helpers, dataclass logic) should
 * extend PHPUnit\Framework\TestCase directly to avoid the boot cost.
 */
abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        /** @var \Illuminate\Contracts\Console\Kernel $kernel */
        $app = require __DIR__ . '/../bootstrap/app.php';
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        return $app;
    }
}
