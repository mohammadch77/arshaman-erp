<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بازسازی process_steps روی SQLite نیاز به rename موقت دارد. رفتار
     * پیش‌فرض SQLite (مستقل از PRAGMA foreign_keys) این است که ALTER TABLE
     * RENAME تعریف FK جداول دیگر (process_transitions/process_instances/
     * process_instance_logs) که به process_steps ارجاع دارند را هم خودکار
     * به نام جدید rewrite می‌کند — یعنی بعد از rename زیر، آن FK ها به
     * «process_steps_old» اشاره می‌کنند، نه «process_steps» جدید؛ با drop
     * شدن جدول old در انتها، آن FK به یک جدول ناموجود دلالت می‌کند. تأیید
     * شده مستقیم با PDO/sqlite: تنها راه جلوگیری PRAGMA legacy_alter_table
     * است (نه foreign_keys — آن پراگما اثری روی این رفتار خاص ندارد).
     */
    public $withinTransaction = false;

    /**
     * استثنای مستند بند ۱۵ docs/DATABASE_CONVENTIONS.md. مرحله‌ی جدید
     * requester_input (تکمیل اطلاعات توسط درخواست‌دهنده‌ی اصلی instance، نه یک
     * نقش/شخص واگذارشده) نیاز به سه مقدار ENUM جدید در سه ستون دارد:
     * - process_steps.step_type: 'requester_input'
     * - process_transitions.on_result: 'default' (فقط برای همین نوع مرحله —
     *   یک مسیر خروجی، نه approve/reject یا condition_true/condition_false دوگانه)
     * - process_instance_logs.action: 'requester_input'
     *
     * روی SQLite (محیط تست) هر سه جدول باید کامل بازسازی شوند (rename →
     * create → copy → drop) — SQLite امکان ALTER/DROP روی یک CHECK موجود را
     * نمی‌دهد؛ همان الگوی مهاجرت قبلی همین بند (افزودن reminder/reversed به
     * process_instance_logs.action).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildStepsSqlite(['start', 'approval', 'condition', 'requester_input', 'end']);
            $this->rebuildTransitionsSqlite(['approved', 'rejected', 'condition_true', 'condition_false', 'default']);
            $this->rebuildLogsSqlite(['approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed', 'requester_input']);

            return;
        }

        DB::statement("ALTER TABLE process_steps MODIFY COLUMN step_type ENUM('start', 'approval', 'condition', 'requester_input', 'end') NOT NULL");
        DB::statement("ALTER TABLE process_transitions MODIFY COLUMN on_result ENUM('approved', 'rejected', 'condition_true', 'condition_false', 'default') NOT NULL");
        DB::statement("ALTER TABLE process_instance_logs MODIFY COLUMN action ENUM('approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed', 'requester_input') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildStepsSqlite(['start', 'approval', 'condition', 'end']);
            $this->rebuildTransitionsSqlite(['approved', 'rejected', 'condition_true', 'condition_false']);
            $this->rebuildLogsSqlite(['approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed']);

            return;
        }

        DB::statement("ALTER TABLE process_instance_logs MODIFY COLUMN action ENUM('approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed') NOT NULL");
        DB::statement("ALTER TABLE process_transitions MODIFY COLUMN on_result ENUM('approved', 'rejected', 'condition_true', 'condition_false') NOT NULL");
        DB::statement("ALTER TABLE process_steps MODIFY COLUMN step_type ENUM('start', 'approval', 'condition', 'end') NOT NULL");
    }

    /**
     * @param  array<int, string>  $stepTypeValues
     */
    private function rebuildStepsSqlite(array $stepTypeValues): void
    {
        // هر دو پراگما با هم لازم‌اند — تأیید شده مستقیم با PDO: تنها یکی از
        // این دو به‌تنهایی (چه legacy_alter_table چه foreign_keys) رفتار
        // rewrite خودکار SQLite را متوقف نمی‌کند، فقط ترکیب هر دو کار می‌کند.
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('PRAGMA legacy_alter_table = ON');
        Schema::rename('process_steps', 'process_steps_old');
        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');

        DB::statement('DROP INDEX IF EXISTS uq_process_steps_definition_key');
        DB::statement('DROP INDEX IF EXISTS idx_process_steps_definition');

        Schema::create('process_steps', function (Blueprint $table) use ($stepTypeValues) {
            $table->uuid('id')->primary();
            $table->uuid('process_definition_id');
            $table->string('step_key', 50);
            $table->string('name', 100);
            $table->enum('step_type', $stepTypeValues);
            $table->enum('assignment_type', ['role', 'specific_user'])->nullable();
            $table->string('assigned_role', 30)->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('condition_field', 60)->nullable();
            $table->enum('condition_operator', ['>', '<', '=', '>=', '<=', '!='])->nullable();
            $table->string('condition_value', 100)->nullable();
            $table->json('step_form_fields')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['process_definition_id', 'step_key'], 'uq_process_steps_definition_key');
            $table->index('process_definition_id', 'idx_process_steps_definition');

            $table->foreign('process_definition_id', 'fk_process_steps_definition')->references('id')->on('process_definitions');
            $table->foreign('assigned_user_id', 'fk_process_steps_assigned_user')->references('id')->on('users');
        });

        $columns = 'id, process_definition_id, step_key, name, step_type, assignment_type, assigned_role, assigned_user_id, condition_field, condition_operator, condition_value, step_form_fields, display_order, created_at, updated_at';

        DB::statement("INSERT INTO process_steps ({$columns}) SELECT {$columns} FROM process_steps_old");

        Schema::drop('process_steps_old');
    }

    /**
     * @param  array<int, string>  $onResultValues
     */
    private function rebuildTransitionsSqlite(array $onResultValues): void
    {
        Schema::rename('process_transitions', 'process_transitions_old');

        DB::statement('DROP INDEX IF EXISTS idx_process_transitions_from');
        DB::statement('DROP INDEX IF EXISTS idx_process_transitions_to');

        Schema::create('process_transitions', function (Blueprint $table) use ($onResultValues) {
            $table->uuid('id')->primary();
            $table->uuid('from_step_id');
            $table->uuid('to_step_id');
            $table->enum('on_result', $onResultValues);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->index('from_step_id', 'idx_process_transitions_from');
            $table->index('to_step_id', 'idx_process_transitions_to');

            $table->foreign('from_step_id', 'fk_process_transitions_from')->references('id')->on('process_steps');
            $table->foreign('to_step_id', 'fk_process_transitions_to')->references('id')->on('process_steps');
        });

        $columns = 'id, from_step_id, to_step_id, on_result, display_order';

        DB::statement("INSERT INTO process_transitions ({$columns}) SELECT {$columns} FROM process_transitions_old");

        Schema::drop('process_transitions_old');
    }

    /**
     * @param  array<int, string>  $actionValues
     */
    private function rebuildLogsSqlite(array $actionValues): void
    {
        Schema::rename('process_instance_logs', 'process_instance_logs_old');

        DB::statement('DROP INDEX IF EXISTS idx_process_instance_logs_company');
        DB::statement('DROP INDEX IF EXISTS idx_process_instance_logs_instance');

        Schema::create('process_instance_logs', function (Blueprint $table) use ($actionValues) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('process_instance_id');
            $table->uuid('step_id');
            $table->uuid('actor_user_id')->nullable();
            $table->enum('action', $actionValues);
            $table->string('comment', 300)->nullable();
            $table->json('step_data')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('owner_company_id', 'idx_process_instance_logs_company');
            $table->index('process_instance_id', 'idx_process_instance_logs_instance');

            $table->foreign('owner_company_id', 'fk_pil_company')->references('id')->on('companies');
            $table->foreign('process_instance_id', 'fk_pil_instance')->references('id')->on('process_instances');
            $table->foreign('step_id', 'fk_pil_step')->references('id')->on('process_steps');
            $table->foreign('actor_user_id', 'fk_pil_actor')->references('id')->on('users');
        });

        $columns = 'id, owner_company_id, process_instance_id, step_id, actor_user_id, action, comment, step_data, reversed_at, created_at';

        DB::statement("INSERT INTO process_instance_logs ({$columns}) SELECT {$columns} FROM process_instance_logs_old");

        Schema::drop('process_instance_logs_old');
    }
};
