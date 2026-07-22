<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds is_locked to service_definitions. A locked service refuses every
 * content mutation (update, updateStatus, chat-schema) at the API layer —
 * an admin or superuser must explicitly unlock the row before edits are
 * accepted. Seeders bypass this because they write via Eloquent directly.
 *
 * Default is TRUE so every existing row is locked out of the box: safer
 * default for a production catalog than "everything is editable".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_definitions', function (Blueprint $table) {
            $table->boolean('is_locked')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('service_definitions', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
