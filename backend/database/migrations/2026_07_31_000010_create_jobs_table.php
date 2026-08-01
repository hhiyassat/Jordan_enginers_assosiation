<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CS-02: create the standard Laravel `jobs` table for the `database`
 * queue driver.
 *
 * Without this table, the code default in `config/queue.php`
 * (`env('QUEUE_CONNECTION', 'database')`) would 500 on the first
 * dispatch in any environment where `QUEUE_CONNECTION` is unset. This
 * migration ships the schema Laravel expects verbatim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
