<?php

declare(strict_types=1);

namespace Modules\JeaServices\Domain\Cutover;

use RuntimeException;

/**
 * TD-10 · Migration dry-run tool.
 *
 * FAIL-CLOSED: defaults to READ_ONLY=YES / WRITE_ENABLED=NO.
 * Callers must explicitly pass `enableWrite: true` — the constructor
 * refuses unless an explicit authorization token is present. This
 * program does NOT grant such tokens; downstream tests can pass
 * `authorizationToken: 'test-authorization-token'` to prove the
 * refusal shape works, but no production caller has this token.
 */
final class MigrationDryRunTool
{
    public const PRODUCTION_AUTHORIZATION_REQUIRED = 'PRODUCTION_WRITE_NOT_YET_AUTHORIZED';

    private readonly bool $writeEnabled;
    private int $wroteRowCount = 0;

    public function __construct(bool $enableWrite = false, ?string $authorizationToken = null)
    {
        if ($enableWrite && $authorizationToken !== 'test-authorization-token') {
            throw new RuntimeException(self::PRODUCTION_AUTHORIZATION_REQUIRED);
        }
        $this->writeEnabled = $enableWrite;
    }

    /**
     * @param array<string, mixed> $historicalApplicationRow
     */
    public function backfillApplication(array $historicalApplicationRow): void
    {
        if (! $this->writeEnabled) {
            // READ_ONLY dry-run — no write. Just record we saw the row.
            return;
        }
        // In test-authorized mode we simulate the write; no real DB
        // update happens because this class holds no repository.
        $this->wroteRowCount++;
    }

    public function writeCount(): int
    {
        return $this->wroteRowCount;
    }

    public function isReadOnly(): bool
    {
        return ! $this->writeEnabled;
    }
}
