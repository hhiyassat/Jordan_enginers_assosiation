<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-08: rolling password-history table. Stores the last N hashes
 * per user so the policy can reject reuse. See PasswordHistory +
 * PasswordPolicy::distinctFromHistoryFor.
 *
 * Schema is intentionally minimal — no PII beyond user_id, no
 * plaintext, no derived fields. Hash column is CHAR(255) to fit
 * every current Laravel hasher (bcrypt is 60, argon2id is up to
 * ~200 depending on parameters).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password_hash', 255);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at'], 'password_history_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('password_history', function (Blueprint $table) {
            $table->dropIndex('password_history_user_created_idx');
        });
        Schema::dropIfExists('password_history');
    }
};
