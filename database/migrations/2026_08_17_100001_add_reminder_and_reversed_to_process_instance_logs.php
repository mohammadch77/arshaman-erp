<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * استثنای مستند بند ۱۵ docs/DATABASE_CONVENTIONS.md: process_instance_logs.action
     * از نوع ENUM نیتیو MySQL است. افزودن مقدار جدید به یک ENUM نیتیو نیاز به
     * ALTER ... MODIFY COLUMN دارد (بازنویسی کل تعریف ستون)، نه یک DROP/ADD CHECK
     * سبک بند ۳.۲. دو مقدار جدید: 'reminder' (یادآوری ادمین بدون تغییر وضعیت) و
     * 'reversed' (علامت‌گذاری متادیتای یک تصمیم تأیید/رد قبلی به‌عنوان بازگردانی‌شده،
     * بدون حذف/ویرایش محتوای رکورد اصلی).
     *
     * روی SQLite (محیط تست) بند ۱۵ می‌گوید Laravel enum() را به VARCHAR+CHECK ترجمه
     * می‌کند — برخلاف CHECK های دستی پروژه که هرگز روی sqlite ساخته نمی‌شوند، این
     * CHECK از قبل روی جدول تست وجود دارد و SQLite امکان ALTER/DROP CHECK را نمی‌دهد؛
     * تنها راه، بازسازی کامل جدول (rename → create → copy → drop) است.
     *
     * ستون nullable جدید `reversed_at`: فقط یک مهر زمانی متادیتا روی رکورد لاگ
     * تأیید/ردِ اصلی، هرگز action/comment/actor موجود را تغییر نمی‌دهد — خودِ
     * بازگردانی یک ردیف لاگ جدید (action=reversed) هم اضافه می‌کند.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(
                ['approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed'],
                withReversedAt: true,
            );

            return;
        }

        DB::statement("ALTER TABLE process_instance_logs MODIFY COLUMN action ENUM('approved', 'rejected', 'condition_evaluated', 'started', 'completed', 'reminder', 'reversed') NOT NULL");

        Schema::table('process_instance_logs', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable(
                ['approved', 'rejected', 'condition_evaluated', 'started', 'completed'],
                withReversedAt: false,
            );

            return;
        }

        Schema::table('process_instance_logs', function (Blueprint $table) {
            $table->dropColumn('reversed_at');
        });

        DB::statement("ALTER TABLE process_instance_logs MODIFY COLUMN action ENUM('approved', 'rejected', 'condition_evaluated', 'started', 'completed') NOT NULL");
    }

    /**
     * @param  array<int, string>  $actionValues
     */
    private function rebuildSqliteTable(array $actionValues, bool $withReversedAt): void
    {
        Schema::rename('process_instance_logs', 'process_instance_logs_old');

        // SQLite نام ایندکس را هنگام RENAME TABLE منتقل نمی‌کند — ایندکس‌های
        // قدیمی هنوز با همان نام روی جدول تغییرنام‌یافته باقی می‌مانند، پس قبل
        // از ساخت جدول جدید با همان نام‌های ایندکس باید صریح حذف شوند.
        DB::statement('DROP INDEX IF EXISTS idx_process_instance_logs_company');
        DB::statement('DROP INDEX IF EXISTS idx_process_instance_logs_instance');

        Schema::create('process_instance_logs', function (Blueprint $table) use ($actionValues, $withReversedAt) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('process_instance_id');
            $table->uuid('step_id');
            $table->uuid('actor_user_id')->nullable();
            $table->enum('action', $actionValues);
            $table->string('comment', 300)->nullable();

            if ($withReversedAt) {
                $table->timestamp('reversed_at')->nullable();
            }

            $table->timestamp('created_at')->useCurrent();

            $table->index('owner_company_id', 'idx_process_instance_logs_company');
            $table->index('process_instance_id', 'idx_process_instance_logs_instance');

            $table->foreign('owner_company_id', 'fk_pil_company')->references('id')->on('companies');
            $table->foreign('process_instance_id', 'fk_pil_instance')->references('id')->on('process_instances');
            $table->foreign('step_id', 'fk_pil_step')->references('id')->on('process_steps');
            $table->foreign('actor_user_id', 'fk_pil_actor')->references('id')->on('users');
        });

        $columns = $withReversedAt
            ? 'id, owner_company_id, process_instance_id, step_id, actor_user_id, action, comment, created_at'
            : 'id, owner_company_id, process_instance_id, step_id, actor_user_id, action, comment, created_at';

        DB::statement("INSERT INTO process_instance_logs ({$columns}) SELECT {$columns} FROM process_instance_logs_old");

        Schema::drop('process_instance_logs_old');
    }
};
