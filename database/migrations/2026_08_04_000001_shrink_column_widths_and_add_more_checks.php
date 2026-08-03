<?php

use App\Modules\HR\Enums\EmployeePosition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // این migration فقط سینتکس MySQL دارد (MODIFY، ADD CONSTRAINT ... CHECK، REGEXP) —
        // همان دلیل migration 2026_08_03_000001. روی sqlite (مجموعه تست پیش‌فرض) کاری
        // انجام نمی‌دهد؛ تست واقعی این CHECK ها فقط روی اتصال mysql معنا دارد.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // --- بخش ۱: طول/نوع ستون‌ها ---

        // Auth
        DB::statement('ALTER TABLE companies MODIFY name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE companies MODIFY slug VARCHAR(20) NOT NULL');
        DB::statement("ALTER TABLE companies MODIFY base_currency CHAR(3) NOT NULL DEFAULT 'IRR'");
        DB::statement('ALTER TABLE users MODIFY full_name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE roles MODIFY name VARCHAR(30) NOT NULL');
        DB::statement('ALTER TABLE roles MODIFY display_name VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE permissions MODIFY name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE permissions MODIFY display_name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE user_invitations MODIFY full_name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE user_invitations MODIFY token CHAR(64) NOT NULL');

        // HR
        DB::statement('ALTER TABLE employees MODIFY full_name VARCHAR(80) NOT NULL');
        DB::statement('ALTER TABLE employees MODIFY phone CHAR(11) NULL');
        DB::statement('ALTER TABLE employees MODIFY address VARCHAR(255) NULL');

        // employees.position: قبل از کوچک‌کردن طول و افزودن CHECK، داده آزاد فعلی به enum
        // جدید نگاشت می‌شود. تنها مقدار موجود در دیتابیس واقعی 'طراح گرافیک' بود — طبق تأیید
        // کارفرما به 'graphic_designer' نگاشت شد.
        DB::table('employees')->where('position', 'طراح گرافیک')->update(['position' => EmployeePosition::GraphicDesigner->value]);
        DB::statement('ALTER TABLE employees MODIFY position VARCHAR(30) NOT NULL');

        DB::statement('ALTER TABLE holidays MODIFY title VARCHAR(60) NOT NULL');
        DB::statement('ALTER TABLE payroll_runs MODIFY period_month CHAR(7) NOT NULL');
        DB::statement('ALTER TABLE monthly_attendance_summaries MODIFY period_month CHAR(7) NOT NULL');
        DB::statement("ALTER TABLE payslips MODIFY expense_posting_status VARCHAR(10) NOT NULL DEFAULT 'pending'");

        // CRM
        DB::statement('ALTER TABLE contacts MODIFY full_name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE contact_site_profiles MODIFY site_full_name VARCHAR(100) NULL');

        // Core
        DB::statement('ALTER TABLE parties MODIFY economic_code CHAR(12) NULL');
        DB::statement('ALTER TABLE currencies MODIFY code CHAR(3) NOT NULL');
        DB::statement('ALTER TABLE currencies MODIFY name VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE fiscal_periods MODIFY name VARCHAR(30) NOT NULL');

        // --- بخش ۲: CHECK های جدید (موارد تکراری با migration قبلی 2026_08_03 اینجا
        // دوباره اضافه نمی‌شوند؛ نگاه کن گزارش نقشه). تیکتینگ/کمپین چون هنوز ساخته
        // نشده‌اند، در BACKLOG.md یادداشت شدند. ---

        $positionValues = "'" . implode("','", array_map(fn (EmployeePosition $p) => $p->value, EmployeePosition::cases())) . "'";
        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_employees_position CHECK (position IN ({$positionValues}))");
        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_employees_phone CHECK (phone IS NULL OR phone REGEXP '^09[0-9]{9}$')");

        DB::statement("ALTER TABLE interactions ADD CONSTRAINT chk_interactions_interaction_type CHECK (interaction_type IN ('call','telegram','site_form','purchase'))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT chk_leads_source CHECK (source IN ('instagram','website','telegram','referral','other'))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT chk_leads_pipeline_stage CHECK (pipeline_stage IN ('new','contacted','qualified','proposal','won','lost'))");
        DB::statement("ALTER TABLE rfm_segments ADD CONSTRAINT chk_rfm_segments_segment CHECK (segment IN ('vip','at_risk','dormant','new'))");
        DB::statement("ALTER TABLE parties ADD CONSTRAINT chk_parties_party_type CHECK (party_type IN ('individual','company'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE employees DROP CHECK chk_employees_position');
        DB::statement('ALTER TABLE employees DROP CHECK chk_employees_phone');
        DB::statement('ALTER TABLE interactions DROP CHECK chk_interactions_interaction_type');
        DB::statement('ALTER TABLE leads DROP CHECK chk_leads_source');
        DB::statement('ALTER TABLE leads DROP CHECK chk_leads_pipeline_stage');
        DB::statement('ALTER TABLE rfm_segments DROP CHECK chk_rfm_segments_segment');
        DB::statement('ALTER TABLE parties DROP CHECK chk_parties_party_type');

        DB::statement('ALTER TABLE companies MODIFY name VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE companies MODIFY slug VARCHAR(50) NOT NULL');
        DB::statement("ALTER TABLE companies MODIFY base_currency VARCHAR(3) NOT NULL DEFAULT 'IRR'");
        DB::statement('ALTER TABLE users MODIFY full_name VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE roles MODIFY name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE roles MODIFY display_name VARCHAR(150) NOT NULL');
        DB::statement('ALTER TABLE permissions MODIFY name VARCHAR(150) NOT NULL');
        DB::statement('ALTER TABLE permissions MODIFY display_name VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE user_invitations MODIFY full_name VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE user_invitations MODIFY token VARCHAR(64) NOT NULL');

        DB::statement('ALTER TABLE employees MODIFY full_name VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE employees MODIFY phone VARCHAR(20) NULL');
        DB::statement('ALTER TABLE employees MODIFY address TEXT NULL');
        DB::statement('ALTER TABLE employees MODIFY position VARCHAR(150) NOT NULL');

        DB::statement('ALTER TABLE holidays MODIFY title VARCHAR(150) NOT NULL');
        DB::statement('ALTER TABLE payroll_runs MODIFY period_month VARCHAR(7) NOT NULL');
        DB::statement('ALTER TABLE monthly_attendance_summaries MODIFY period_month VARCHAR(7) NOT NULL');
        DB::statement("ALTER TABLE payslips MODIFY expense_posting_status VARCHAR(20) NOT NULL DEFAULT 'pending'");

        DB::statement('ALTER TABLE contacts MODIFY full_name VARCHAR(200) NOT NULL');
        DB::statement('ALTER TABLE contact_site_profiles MODIFY site_full_name VARCHAR(200) NULL');

        DB::statement('ALTER TABLE parties MODIFY economic_code VARCHAR(30) NULL');
        DB::statement('ALTER TABLE currencies MODIFY code VARCHAR(3) NOT NULL');
        DB::statement('ALTER TABLE currencies MODIFY name VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE fiscal_periods MODIFY name VARCHAR(100) NOT NULL');
    }
};
