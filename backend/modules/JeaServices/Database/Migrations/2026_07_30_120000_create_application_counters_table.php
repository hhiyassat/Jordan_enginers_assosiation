<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore

/**
 * H-02: per-service, per-year counter for application reference numbers.
 *
 * The previous Application::generateReference() did
 * `Application::withoutOrgScope()->where('service_definition_id', $id)
 *   ->where('created_at', '>=', $yearStart)->count() + 1`
 * which is the same read-modify-write race that JORD-2 fixed for
 * certificate numbers: two concurrent submits both saw N applications
 * for that (service, year), both formatted N+1, and one insert died
 * on the reference_number unique index.
 *
 * This counter pairs with an atomic INSERT-if-missing + SELECT FOR
 * UPDATE + increment inside generateReference(). Two concurrent
 * creates serialize cleanly on the lock and each gets a distinct
 * sequence number.
 *
 * Ownership: this counter is per-service (services are catalog
 * entities, not tenant-owned), so it is intentionally NOT scoped by
 * organization_id — matches the shape of certificate_counters, whose
 * only difference is the (org, year) key vs (service, year).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('application_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_serial')->default(1);
            $table->timestamps();

            // One counter per (service, year). Absent this, the counter
            // can't guarantee monotonicity — two concurrent inserts
            // would create two rows and both allocate serial=1.
            $table->unique(['service_definition_id', 'year'], 'app_counter_service_year_unique');
        });

        // Backfill: seed each (service, year) counter from the highest
        // pre-existing reference number so post-migration allocations
        // don't collide with legacy count()+1 references. Skipped if
        // there are no applications yet (fresh install).
        //
        // Driver-agnostic: fetch minimal columns and aggregate in PHP.
        // Cost is O(rows), only run once at migration time; the query
        // returns three columns for however many application rows exist,
        // typically well under 100k even in mature deployments.
        if (!Schema::hasTable('applications')) {
            return;
        }

        $agg  = [];
        $rows = DB::table('applications')
            ->select(['service_definition_id', 'reference_number', 'created_at'])
            ->get();
        foreach ($rows as $row) {
            $ref = (string) $row->reference_number;
            if (!preg_match('/^\d{10}$/', $ref)) {
                continue; // legacy alpha references (ESP-XXX-…) don't feed the numeric counter
            }
            $seq  = (int) substr($ref, 6, 4);
            $year = (int) date('Y', strtotime((string) $row->created_at));
            $key  = "{$row->service_definition_id}:{$year}";
            if (!isset($agg[$key]) || $agg[$key]['max_seq'] < $seq) {
                $agg[$key] = [
                    'service_definition_id' => (int) $row->service_definition_id,
                    'year'                  => $year,
                    'max_seq'               => $seq,
                ];
            }
        }

        $now = now();
        foreach ($agg as $g) {
            DB::table('application_counters')->insert([
                'service_definition_id' => $g['service_definition_id'],
                'year'                  => $g['year'],
                'next_serial'           => $g['max_seq'] + 1,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_counters');
    }
};
