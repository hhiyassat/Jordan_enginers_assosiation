<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JORD-2: per-org, per-year counter for certificate numbers.
 *
 * The previous generator did `Certificate::count() + 1` which is a
 * classic read-modify-write race: two concurrent issueCertificate calls
 * both saw N, both formatted N+1, and one of the inserts got rejected
 * by the certificate_number unique index (rolling back the whole issue
 * transaction). Under load that failure surfaced as sporadic 500s at
 * the tail of the review flow.
 *
 * With this counter, generateCertificateNumber() takes a SELECT ... FOR
 * UPDATE lock on the (organization_id, year) row, reads next_serial,
 * increments, saves — atomically inside the enclosing issue transaction.
 * Two concurrent issues serialize cleanly on the lock and each gets a
 * distinct serial.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('certificate_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_serial')->default(1);
            $table->timestamps();

            // One counter per (org, year). Absent this the counter can't
            // guarantee monotonicity — two concurrent firstOrCreate calls
            // would create two rows and both allocate serial=1.
            $table->unique(['organization_id', 'year'], 'cert_counter_org_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_counters');
    }
};
