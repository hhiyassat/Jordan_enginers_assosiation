<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-05 / H-09: Add indexed basin_number and parcel_number columns to applications table.
 * Replaces json_extract(...) query with portable Eloquent operations across PostgreSQL, MySQL, and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('basin_number')->nullable()->index();
            $table->string('parcel_number')->nullable()->index();
            $table->index(['basin_number', 'parcel_number', 'status'], 'apps_cadastral_status_idx');
        });

        // Backfill existing applications with cadastral data from JSON
        if (Schema::hasTable('applications')) {
            DB::table('applications')->whereNotNull('data')->orderBy('id')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $data = is_string($row->data) ? json_decode($row->data, true) : (array) $row->data;
                    if (is_array($data)) {
                        $basin = isset($data['basin_number']) && $data['basin_number'] !== '' ? (string) $data['basin_number'] : null;
                        $parcel = isset($data['parcel_number']) && $data['parcel_number'] !== '' ? (string) $data['parcel_number'] : null;
                        if ($basin !== null || $parcel !== null) {
                            DB::table('applications')->where('id', $row->id)->update([
                                'basin_number' => $basin,
                                'parcel_number' => $parcel,
                            ]);
                        }
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Drop the compound index first, then the auto-named single-column
        // indexes created by ->index() in up(), THEN drop the columns.
        // SQLite auto-fails a dropColumn if any surviving index still
        // references the column being dropped.
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('apps_cadastral_status_idx');
            $table->dropIndex(['basin_number']);   // resolves to applications_basin_number_index
            $table->dropIndex(['parcel_number']);  // resolves to applications_parcel_number_index
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['basin_number', 'parcel_number']);
        });
    }
};
