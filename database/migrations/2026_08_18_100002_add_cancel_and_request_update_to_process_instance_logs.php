<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * همان دلیل مهاجرت قبلی همین بند (افزودن requester_input): بازسازی جدول
     * روی SQLite نیاز به rename موقت دارد که بدون خاموش‌کردن تراکنش دور آن،
     * رفتار پیش‌فرض SQLite ارجاع FK جداول دیگر را به نام موقت rewrite می‌کند.
     */
    public $withinTransaction = false;

    /**
     * استثنای مستند بند ۱۵ docs/DATABASE_CONVENTIONS.md. دو مقدار جدید برای
     * process_instance_logs.action (بخش ۳ Session جاری — ویرایش/لغو درخواست
     * توسط فرستنده‌ی اصلی، قبل از این‌که مرحله‌ی فعلی هیچ اقدامی داشته باشد):
     * - 'request_updated': فرستنده request_data فرایند آزاد را ویرایش کرد
     * - 'cancelled': فرستنده کل instance را لغو کرد
     */
    public function up(): void
    {
        $values = ['approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed', 'requester_input', 'request_updated', 'cancelled'];

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildLogsSqlite($values);

            return;
        }

        DB::statement("ALTER TABLE process_instance_logs MODIFY COLUMN action ENUM('".implode("', '", $values)."') NOT NULL");
    }

    public function down(): void
    {
        $values = ['approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed', 'requester_input'];

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildLogsSqlite($values);

            return;
        }

        DB::statement("ALTER TABLE process_instance_logs MODIFY COLUMN action ENUM('".implode("', '", $values)."') NOT NULL");
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
