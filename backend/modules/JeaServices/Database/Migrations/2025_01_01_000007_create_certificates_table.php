<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-006: Certificate issued only after full workflow approval.
 * DATA-005: QR token is SHA-256 HMAC-signed.
 * FR-013: Public certificate verification endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->string('qr_token', 64)->unique();   // SHA-256 HMAC for tamper detection
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->date('issued_date');
            $table->date('expiry_date');
            $table->json('cert_data')->nullable();       // fields_on_cert snapshot from schema
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
