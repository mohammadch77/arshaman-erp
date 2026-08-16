<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * استثنای مستند بند ۱۴/۱۵ docs/DATABASE_CONVENTIONS.md: step_type/assignment_type/
     * condition_operator با تصمیم صریح کارفرما از نوع ENUM نیتیو MySQL هستند، نه
     * VARCHAR+CHECK دستی. Laravel enum() خودش روی mysql واقعاً ENUM و روی sqlite
     * (محیط تست) به CHECK ترجمه می‌کند، پس نیازی به گارد سطح درایور نیست.
     */
    public function up(): void
    {
        Schema::create('process_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('process_definition_id');
            $table->string('step_key', 50);
            $table->string('name', 100);
            $table->enum('step_type', ['start', 'approval', 'condition', 'end']);
            $table->enum('assignment_type', ['role', 'specific_user'])->nullable();
            $table->string('assigned_role', 30)->nullable();
            $table->uuid('assigned_user_id')->nullable();
            // فقط از whitelist ثبت‌شده در config/processes.php، نه تایپ آزاد کاربر.
            $table->string('condition_field', 60)->nullable();
            $table->enum('condition_operator', ['>', '<', '=', '>=', '<=', '!='])->nullable();
            $table->string('condition_value', 100)->nullable();
            $table->timestamps();

            $table->unique(['process_definition_id', 'step_key'], 'uq_process_steps_definition_key');
            $table->index('process_definition_id', 'idx_process_steps_definition');

            $table->foreign('process_definition_id', 'fk_process_steps_definition')->references('id')->on('process_definitions');
            $table->foreign('assigned_user_id', 'fk_process_steps_assigned_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_steps');
    }
};
