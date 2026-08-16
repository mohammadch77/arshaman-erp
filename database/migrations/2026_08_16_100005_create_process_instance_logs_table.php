<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_instance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // snapshot صریح (نه BelongsToCompany) — طبق بند ۳ CLAUDE.md برای جداول
            // عملیاتی، الگوی دقیق contact_submission_attempts.owner_company_id.
            $table->uuid('owner_company_id');
            $table->uuid('process_instance_id');
            $table->uuid('step_id');
            // خالی اگر خودکار/ارزیابی شرط بوده، نه یک اقدام دستی کاربر.
            $table->uuid('actor_user_id')->nullable();
            $table->enum('action', ['approved', 'rejected', 'condition_evaluated', 'started', 'completed']);
            $table->string('comment', 300)->nullable();
            // بدون updated_at — رکورد لاگ غیرقابل‌ویرایش.
            $table->timestamp('created_at')->useCurrent();

            $table->index('owner_company_id', 'idx_process_instance_logs_company');
            $table->index('process_instance_id', 'idx_process_instance_logs_instance');

            $table->foreign('owner_company_id', 'fk_pil_company')->references('id')->on('companies');
            $table->foreign('process_instance_id', 'fk_pil_instance')->references('id')->on('process_instances');
            $table->foreign('step_id', 'fk_pil_step')->references('id')->on('process_steps');
            $table->foreign('actor_user_id', 'fk_pil_actor')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_instance_logs');
    }
};
