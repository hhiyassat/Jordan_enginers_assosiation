<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_cycles', function (Blueprint $table) {
            $table->id();

            // Unique cycle reference (e.g. ESP-CYCLE-0001)
            $table->string('cycle_ref', 50)->unique();

            // Inbound data from Nashmi
            $table->string('service_name', 255);
            $table->string('requirements_source', 100)->default('nashmi-requirement-ai');
            $table->string('requirements_file_path')->nullable();   // stored PDF path
            $table->json('requirements_meta')->nullable();           // description, received_ip, etc.

            // Cycle lifecycle
            // Statuses: requirements_received → code_done → feedback_received → closed
            $table->string('status', 50)->default('requirements_received')->index();

            // Outbound data to Nashmi
            $table->unsignedBigInteger('nashmi_project_id')->nullable();  // Nashmi project ID after push
            $table->json('code_summary')->nullable();                      // git, files, endpoints, pages

            // Feedback received from Nashmi
            $table->json('feedback')->nullable();

            // Free-text notes for internal use
            $table->text('notes')->nullable();

            // Timestamps for each lifecycle event
            $table->timestamp('requirements_received_at')->nullable();
            $table->timestamp('code_done_notified_at')->nullable();
            $table->timestamp('feedback_received_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_cycles');
    }
};
