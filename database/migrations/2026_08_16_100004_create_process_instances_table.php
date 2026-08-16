<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('process_definition_id');
            // پلی‌مورفیک: هر دو پر (وصل به ماژول) یا هر دو خالی (فرایند آزاد).
            $table->string('subject_type', 100)->nullable();
            $table->char('subject_id', 36)->nullable();
            // فقط برای فرایند آزاد — پاسخ کاربر به request_form_fields تعریف.
            $table->json('request_data')->nullable();
            $table->uuid('current_step_id')->nullable();
            $table->enum('status', ['in_progress', 'approved', 'rejected', 'cancelled'])->default('in_progress');
            $table->uuid('started_by_user_id');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->index(['subject_type', 'subject_id'], 'idx_process_instances_subject');
            $table->index(['owner_company_id', 'status'], 'idx_process_instances_company_status');

            $table->foreign('owner_company_id', 'fk_process_instances_company')->references('id')->on('companies');
            $table->foreign('process_definition_id', 'fk_process_instances_definition')->references('id')->on('process_definitions');
            $table->foreign('current_step_id', 'fk_process_instances_current_step')->references('id')->on('process_steps');
            $table->foreign('started_by_user_id', 'fk_process_instances_started_by')->references('id')->on('users');
        });

        // لایه دفاعی دوم سطح دیتابیس، طبق الگوی CHECK constraint های موجود —
        // SQLite (محیط تست) این سینتکس را پشتیبانی نمی‌کند.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE process_instances ADD CONSTRAINT chk_process_instances_subject_pair CHECK ((subject_type IS NULL AND subject_id IS NULL) OR (subject_type IS NOT NULL AND subject_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('process_instances');
    }
};
