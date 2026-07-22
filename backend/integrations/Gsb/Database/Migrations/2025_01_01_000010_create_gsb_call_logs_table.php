<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSB Call Log — MODEE Annex 4.15 §4.5 rule 10, §4.9
 *
 * Mandatory audit trail for every call made through the Government Service Bus.
 * Retention: minimum 180 days (§4.9.3). Pruned via 'gsb:prune-logs' command.
 *
 * Required fields per policy:
 *   - API URL                (§4.5 rule 10)
 *   - Time and date          (§4.5 rule 10)
 *   - Real source address    (§4.5 rule 10)
 *   - User identification    (§4.5 rule 10)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsb_call_logs', function (Blueprint $table) {
            $table->id();

            // ── Mandatory fields §4.5 rule 10 ────────────────────────
            $table->string('gsb_endpoint');          // full GSB API URL called
            $table->string('http_method', 10);       // GET, POST, etc.
            $table->string('source_ip', 45);         // real source IP (IPv4/IPv6)
            $table->string('user_identifier')->nullable(); // user ID or service account
            $table->unsignedBigInteger('user_id')->nullable(); // FK to users if authenticated

            // ── Request metadata ──────────────────────────────────────
            $table->string('service_name')->nullable();     // which esp-v2 service triggered this
            $table->string('operation')->nullable();         // logical operation name (e.g. citizen_lookup)
            $table->boolean('is_citizen_data')->default(false); // citizen data endpoint (§4.5 rule 7)
            $table->boolean('otp_verified')->default(false);    // OTP step completed (§4.5 rule 7)

            // ── Response ──────────────────────────────────────────────
            $table->unsignedSmallInteger('response_status')->nullable(); // HTTP status code
            $table->boolean('success')->default(false);
            $table->text('error_code')->nullable();          // generic error code only — no PII (§4.8.1)

            // ── Security flags ────────────────────────────────────────
            $table->boolean('ip_whitelisted')->default(true);  // was source IP on whitelist? (§4.5 rule 11)
            $table->boolean('bulk_request')->default(false);   // bulk data flag (§4.5 rule 16)
            $table->boolean('committee_approved')->nullable();  // null = N/A, true = approved (§4.5 rule 16)

            // ── Performance ───────────────────────────────────────────
            $table->unsignedInteger('duration_ms')->nullable(); // call duration in milliseconds

            // ── Retention anchor — used by gsb:prune-logs ─────────────
            $table->timestamp('logged_at');                    // explicit timestamp for retention queries
            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────
            $table->index('logged_at');          // for 180-day retention pruning
            $table->index('user_id');            // audit queries by user
            $table->index('source_ip');          // anomaly detection by IP
            $table->index('gsb_endpoint');       // usage analytics per endpoint
            $table->index('success');            // failure rate monitoring
            $table->index(['is_citizen_data', 'logged_at']); // citizen data access reports
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsb_call_logs');
    }
};
