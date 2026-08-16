<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 100);
            $table->string('process_key', 50);
            // NULL یعنی این تعریف به یک مدل ماژول دیگر وصل است؛ پر یعنی فرایند آزاد است.
            $table->string('subject_type', 100)->nullable();
            // فقط برای فرایند آزاد — همان ساختار editable_fields ماژول SiteBuilder.
            $table->json('request_form_fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['owner_company_id', 'process_key'], 'uq_process_definitions_company_key');
            $table->index('owner_company_id', 'idx_process_definitions_company');

            $table->foreign('owner_company_id', 'fk_process_definitions_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_process_definitions_created_by')->references('id')->on('users');
        });

        // لایه دفاعی دوم سطح دیتابیس: یا وصل به ماژول (subject_type) یا فرایند آزاد
        // (request_form_fields)، هرگز هر دو هم‌زمان. SQLite (محیط تست) این سینتکس
        // را پشتیبانی نمی‌کند، پس فقط روی mysql واقعی اعمال می‌شود.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE process_definitions ADD CONSTRAINT chk_process_definitions_subject_or_form CHECK (subject_type IS NULL OR request_form_fields IS NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('process_definitions');
    }
};
