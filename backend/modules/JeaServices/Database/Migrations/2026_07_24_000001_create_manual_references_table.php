<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * manual_references — بنية معلومات الأساس النظامي.
 *
 * كل صف = قاعدة واحدة من كتاب التعليمات الفنية 2025، مربوطة بمكان
 * تنفيذها في النظام (خدمة، حقل، أو قاعدة workflow). يُعرَض النص
 * للمستخدمين عبر أيقونة (?) في الواجهة، وقابل للتعديل من قبل admin
 * أثناء المراجعة مع النقابة.
 *
 * إذا عدَّل admin النصَّ، يرفع الصف `needs_reimplementation=true`
 * ويظهر في admin dashboard كـ pending flag يذكِّر الفريق التقني
 * بمراجعة الكود المُطبَّق على هذه القاعدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_references', function (Blueprint $table) {
            $table->id();

            // Provenance — from where in the manual
            $table->string('chapter', 100)->nullable();
            $table->string('section', 200)->nullable();
            $table->unsignedSmallInteger('page')->nullable();
            $table->string('rule_type', 50)->nullable(); // fee, document, workflow, deadline, quota, discipline, ...

            // The rule text — editable
            $table->text('text_ar');
            $table->text('text_ar_original'); // frozen at seed time — for diff detection
            $table->string('jord_ticket', 20)->nullable(); // JORD-XX

            // Where this reference attaches in the platform
            // target_type: 'field' | 'service' | 'rule' | 'workflow_stage' | 'document'
            $table->string('target_type', 20);
            // target_id interpretation depends on target_type:
            //   field           → schema.fields.<id> — plus target_service_code
            //   service         → target_service_code
            //   rule            → free identifier (e.g. 'supervision_expiry')
            //   workflow_stage  → target_service_code + stage id
            //   document        → schema.documents.<id> — plus target_service_code
            $table->string('target_id', 100)->nullable();
            $table->string('target_service_code', 20)->nullable();

            // Edit tracking
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->boolean('needs_reimplementation')->default(false);
            $table->text('edit_reason')->nullable();

            $table->timestamps();

            // Query patterns:
            //   - list refs for a service form  → target_service_code
            //   - list pending edits            → needs_reimplementation
            $table->index(['target_service_code', 'target_type']);
            $table->index('needs_reimplementation');
            $table->index('jord_ticket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_references');
    }
};
