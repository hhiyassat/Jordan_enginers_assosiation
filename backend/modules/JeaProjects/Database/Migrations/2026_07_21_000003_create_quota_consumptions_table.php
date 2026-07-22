<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-68: per-application quota consumption ledger.
 *
 * One row per (approved application × engineer × discipline). The
 * QuotaLedger service inserts on final approval and deletes on
 * application soft-delete so the office's remaining quota reflects
 * live state.
 *
 * (engineer_id, application_id, discipline) composite unique
 * prevents double-consumption if a decide() call is retried after
 * an infrastructure blip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_consumptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->foreignId('engineer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('discipline', 32);        // Disciplines::* canonical
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('m2');
            $t->timestamps();

            $t->unique(['application_id', 'engineer_id', 'discipline']);
            $t->index(['organization_id', 'discipline', 'year']);
            $t->index(['engineer_id', 'discipline', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_consumptions');
    }
};
