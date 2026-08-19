<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بخش ۲ بازطراحی — جایگزین واقعی process_definitions.request_form_fields
     * و process_steps.step_form_fields (هر دو JSON) با ردیف‌های واقعی. پلی‌مورفیک
     * محض (formable_type/formable_id) بدون FK واقعی روی formable_id — دقیقاً
     * همان الگوی subject_type/subject_id در process_instances که هم بدون FK
     * هستند، چون نمی‌توان یک ستون را هم‌زمان FK دو جدول متفاوت کرد.
     *
     * استثنای مستند بند ۱۴/۱۵ docs/DATABASE_CONVENTIONS.md: formable_type و
     * field_type از نوع ENUM نیتیو MySQL هستند (نه VARCHAR+CHECK استاندارد)،
     * هم‌راستا با بقیه‌ی ستون‌های enum-like همین ماژول.
     */
    public function up(): void
    {
        Schema::create('process_form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('formable_type', ['process_definition', 'process_step']);
            $table->uuid('formable_id');
            $table->string('field_key', 60);
            $table->string('label', 150);
            $table->enum('field_type', ['text', 'number', 'textarea', 'file', 'select', 'boolean']);
            $table->boolean('is_required')->default(true);
            // فقط برای field_type='select' — آرایه‌ی [{value,label}].
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['formable_type', 'formable_id', 'field_key'], 'uq_process_form_fields_formable_key');
            $table->index(['formable_type', 'formable_id'], 'idx_process_form_fields_formable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_form_fields');
    }
};
