<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * StorageServiceProvider — NFR-010
 *
 * Enforces object-storage-only for uploads in production. If the default
 * filesystem disk is 'local' or 'public' while APP_ENV=production, we fail
 * fast at boot so misconfigured deploys never accept a user upload onto the
 * app server's disk.
 *
 * Non-production environments log a warning but do not abort — so dev,
 * testing, and CI can still run with the local disk.
 *
 * Approved object-storage drivers: s3, r2 (Cloudflare), spaces (DO), minio.
 */
class StorageServiceProvider extends ServiceProvider
{
    /** Drivers that satisfy NFR-010 (object storage). */
    private const OBJECT_STORAGE_DRIVERS = ['s3', 'r2', 'spaces', 'minio'];

    public function boot(): void
    {
        $defaultDisk = config('filesystems.default');
        $driver      = config("filesystems.disks.{$defaultDisk}.driver");

        if (in_array($driver, self::OBJECT_STORAGE_DRIVERS, true)) {
            return;
        }

        $message = "NFR-010 violation: default filesystem disk '{$defaultDisk}' (driver '{$driver}') is not object storage. "
                 . 'Set FILESYSTEM_DISK to an object-storage driver ('
                 . implode('/', self::OBJECT_STORAGE_DRIVERS) . ').';

        if ($this->app->environment('production')) {
            throw new \RuntimeException($message);
        }

        // Dev / testing / staging: warn but keep running so local development
        // does not require an S3 bucket. Warning is captured in api_access.
        Log::warning($message . ' (allowed outside production; running with fallback disk.)');
    }
}
