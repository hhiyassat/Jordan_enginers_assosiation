<?php

declare(strict_types=1);

namespace Modules\JeaServices\Engine;

use Illuminate\Support\Facades\DB;
use Modules\JeaServices\Models\Application;

/**
 * CrossCuttingSubmissionPipeline — runs every registered
 * CrossCuttingSubmissionGuard for the current application, in order.
 *
 * Returns the FIRST non-empty error array to stop the submission at the
 * first failing gate; the guards themselves decide whether they are in
 * scope for the given application. A guard out of scope returns [] and
 * pipeline continues to the next.
 *
 * ApplicationController::submit invokes this via
 *   app(CrossCuttingSubmissionPipeline::class)->validate($app);
 * between schema validation and the per-service ServiceSubmissionGuardRegistry.
 *
 * The registration order matters — guards defined first are checked first.
 * See JeaServicesServiceProvider::register() for the current chain.
 */
class CrossCuttingSubmissionPipeline
{
    /**
     * @param  list<CrossCuttingSubmissionGuard>  $guards
     */
    public function __construct(private readonly array $guards = []) {}

    /**
     * @return array<string, string>  field-id keyed error messages, [] on pass
     */
    public function validate(Application $app): array
    {
        // C-04 hardening (session 3): serialize any concurrent submit
        // that targets the same cadastral triple by acquiring a
        // PostgreSQL advisory lock keyed on the triple. Without this,
        // two concurrent submits from different orgs on the same
        // parcel both read zero conflicts (neither has committed yet)
        // and both pass — the exact TOCTOU race the concurrency test
        // caught. The lock is per-triple, so unrelated submits do not
        // serialize against each other.
        //
        // On non-Postgres drivers (sqlite in tests) this is a no-op;
        // the driver's coarser locking already prevents concurrent
        // writers in practice.
        $this->acquireCadastralAdvisoryLockFor($app);

        foreach ($this->guards as $guard) {
            $errors = $guard->validate($app);
            if (! empty($errors)) {
                return $errors;
            }
        }
        return [];
    }

    private function acquireCadastralAdvisoryLockFor(Application $app): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        $triple = CadastralPriorApplicationLookup::extractTriple($app);
        if ($triple === null) {
            return; // guard reports the missing-field error itself
        }
        // 63-bit int derived from the cadastral triple. Advisory locks
        // scope to the current transaction (pg_advisory_xact_lock) so
        // they release on COMMIT/ROLLBACK — no explicit unlock needed.
        $key = crc32($triple['basin_number'] . '|' . $triple['parcel_number']);
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$key]);
    }

    /** @return list<string>  class names of registered guards, for introspection */
    public function registeredGuards(): array
    {
        return array_map(static fn ($g) => $g::class, $this->guards);
    }
}
