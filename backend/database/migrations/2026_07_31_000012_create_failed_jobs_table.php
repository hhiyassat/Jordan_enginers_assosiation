<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CS-02: standard Laravel `failed_jobs` schema.
 *
 * `config/queue.php` sets `failed.driver = database-uuids` by default,
 * so this table is required for `queue:failed`, `queue:retry`, and
 * `queue:flush` to work. Without it, a permanently-failing job would
 * throw a "no such table" exception inside the framework instead of
 * being persisted for later inspection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
