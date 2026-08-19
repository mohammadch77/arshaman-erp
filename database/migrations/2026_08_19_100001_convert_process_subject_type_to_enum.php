<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * همان دلیل مهاجرت‌های قبلی همین ماژول (نگاه کن 2026_08_18_100001):
     * بازسازی جدول روی SQLite نیاز به rename موقت دارد که بدون خاموش‌کردن
     * تراکنش دور آن، رفتار پیش‌فرض SQLite ارجاع FK جداول فرزند را به نام موقت
     * rewrite می‌کند.
     */
    public $withinTransaction = false;

    /**
     * مقدار مجاز فعلی ENUM — دقیقاً همان FQCN ذخیره‌شده در ستون (نه نام کوتاه
     * کلاس)، چون subject_type مستقیم `Leave::class` را ذخیره می‌کند (نگاه کن
     * config('processes.subject_types')).
     */
    private const LEAVE_SUBJECT_TYPE = 'App\Modules\HR\Models\Leave';

    /**
     * بخش ۱ بازطراحی — subject_type از VARCHAR(100) آزاد به ENUM('Leave')
     * محدود می‌شود، هم در process_definitions هم process_instances (طبق
     * docs/DATABASE_CONVENTIONS.md بند ۱۶). روی mysql فقط یک
     * ALTER ... MODIFY COLUMN ساده کافی است (نه rebuild، چون هیچ ستون
     * دیگری تغییر نمی‌کند)؛ روی SQLite هر دو جدول با تکنیک rename+PRAGMA
     * بازسازی می‌شوند — این‌ها هم پدر (process_definitions ← process_steps/
     * process_instances) هم فرزند (process_instances ← process_instance_logs)
     * هستند، اما چون فقط با rename موقت (نه drop واقعی) کار می‌کنیم و
     * legacy_alter_table ارجاع فرزندان را دست‌نخورده نگه می‌دارد، بازسازی هرکدام
     * مستقل از دیگری امن است.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildDefinitionsSqlite();
            $this->rebuildInstancesSqlite();

            return;
        }

        // مهم: در یک رشته‌ی SQL خام mysql، بک‌اسلش کاراکتر escape است (مگر
        // sql_mode=NO_BACKSLASH_ESCAPES) — باید هر بک‌اسلش FQCN دوبل شود
        // (\\ ← \)، وگرنه mysql بی‌صدا آن‌ها را حذف می‌کند (کشف‌شده حین اجرای
        // واقعی: enum ساخته‌شده 'AppModulesHRModelsLeave' بدون بک‌اسلش بود،
        // پس insert واقعی 'App\Modules\HR\Models\Leave' با «Data truncated»
        // رد می‌شد).
        $escapedValue = str_replace('\\', '\\\\', self::LEAVE_SUBJECT_TYPE);
        DB::statement("ALTER TABLE process_definitions MODIFY COLUMN subject_type ENUM('{$escapedValue}') NULL");
        DB::statement("ALTER TABLE process_instances MODIFY COLUMN subject_type ENUM('{$escapedValue}') NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildDefinitionsSqlite(toVarchar: true);
            $this->rebuildInstancesSqlite(toVarchar: true);

            return;
        }

        DB::statement('ALTER TABLE process_definitions MODIFY COLUMN subject_type VARCHAR(100) NULL');
        DB::statement('ALTER TABLE process_instances MODIFY COLUMN subject_type VARCHAR(100) NULL');
    }

    private function rebuildDefinitionsSqlite(bool $toVarchar = false): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('PRAGMA legacy_alter_table = ON');
        Schema::rename('process_definitions', 'process_definitions_old');
        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');

        DB::statement('DROP INDEX IF EXISTS uq_process_definitions_company_key_version');
        DB::statement('DROP INDEX IF EXISTS idx_process_definitions_current_version');
        DB::statement('DROP INDEX IF EXISTS idx_process_definitions_company');

        Schema::create('process_definitions', function (Blueprint $table) use ($toVarchar) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 100);
            $table->string('process_key', 50);
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_current_version')->default(true);

            if ($toVarchar) {
                $table->string('subject_type', 100)->nullable();
            } else {
                $table->enum('subject_type', [self::LEAVE_SUBJECT_TYPE])->nullable();
            }

            $table->json('request_form_fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_company_id', 'process_key', 'version'], 'uq_process_definitions_company_key_version');
            $table->index(['owner_company_id', 'process_key', 'is_current_version'], 'idx_process_definitions_current_version');
            $table->index('owner_company_id', 'idx_process_definitions_company');

            $table->foreign('owner_company_id', 'fk_process_definitions_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_process_definitions_created_by')->references('id')->on('users');
        });

        $columns = 'id, owner_company_id, name, process_key, version, is_current_version, subject_type, request_form_fields, is_active, created_by_user_id, created_at, updated_at, deleted_at';

        DB::statement("INSERT INTO process_definitions ({$columns}) SELECT {$columns} FROM process_definitions_old");

        // همان الگوی migration اصلی: CHECK دستی subject_or_form هرگز روی
        // sqlite ساخته نشده (guard غیر-sqlite در همان migration)، پس اینجا
        // هم چیزی برای بازسازی‌اش نیست.
        Schema::drop('process_definitions_old');
    }

    private function rebuildInstancesSqlite(bool $toVarchar = false): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('PRAGMA legacy_alter_table = ON');
        Schema::rename('process_instances', 'process_instances_old');
        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');

        DB::statement('DROP INDEX IF EXISTS idx_process_instances_subject');
        DB::statement('DROP INDEX IF EXISTS idx_process_instances_company_status');

        Schema::create('process_instances', function (Blueprint $table) use ($toVarchar) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('process_definition_id');

            if ($toVarchar) {
                $table->string('subject_type', 100)->nullable();
            } else {
                $table->enum('subject_type', [self::LEAVE_SUBJECT_TYPE])->nullable();
            }

            $table->char('subject_id', 36)->nullable();
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

        $columns = 'id, owner_company_id, process_definition_id, subject_type, subject_id, request_data, current_step_id, status, started_by_user_id, started_at, completed_at';

        DB::statement("INSERT INTO process_instances ({$columns}) SELECT {$columns} FROM process_instances_old");

        // همان الگو: CHECK دستی subject_pair هرگز روی sqlite ساخته نشده.
        Schema::drop('process_instances_old');
    }
};
