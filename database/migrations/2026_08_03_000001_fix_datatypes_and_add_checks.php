<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // این migration فقط سینتکس MySQL دارد (MODIFY، ADD CONSTRAINT ... CHECK، REGEXP).
        // مجموعه تست پیش‌فرض پروژه (phpunit.xml) روی sqlite در حافظه اجرا می‌شود که این
        // سینتکس را نمی‌شناسد؛ برای اینکه اجرای `php artisan test` معمولی نشکند، این
        // migration روی sqlite کاری انجام نمی‌دهد. تست CHECK واقعی فقط روی اتصال mysql
        // معنا دارد — نگاه کن tests/Feature/Core/DatabaseCheckConstraintsTest.php.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // employees.national_id: طول همیشه دقیقاً ۱۰ رقم (کد ملی ایران) — VARCHAR(10) → CHAR(10)
        DB::statement('ALTER TABLE employees MODIFY national_id CHAR(10) NOT NULL');

        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_employees_employment_status CHECK (employment_status IN ('active','on_leave','terminated'))");
        DB::statement("ALTER TABLE employees ADD CONSTRAINT chk_employees_contract_type CHECK (contract_type IN ('permanent','temporary','project_based'))");

        DB::statement("ALTER TABLE attendances ADD CONSTRAINT chk_attendances_recorded_by CHECK (recorded_by IN ('self','admin'))");

        // hourly در لیست اولیه نبود؛ طبق تصمیم کارفرما بعد از کشف داده واقعی
        // (رکورد 019fbc20-a7a0-72ac-9ed7-625c81af5b0a) اضافه شد — نگاه کن
        // 2026_07_29_100012_add_hourly_fields_to_leaves_table.php.
        DB::statement("ALTER TABLE leaves ADD CONSTRAINT chk_leaves_leave_type CHECK (leave_type IN ('annual','sick','unpaid','hourly'))");
        DB::statement("ALTER TABLE leaves ADD CONSTRAINT chk_leaves_leave_status CHECK (leave_status IN ('pending','approved','rejected'))");

        DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT chk_payroll_runs_payroll_status CHECK (payroll_status IN ('draft','calculated','finalized'))");
        DB::statement("ALTER TABLE payroll_runs ADD CONSTRAINT chk_payroll_runs_period_month CHECK (period_month REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')");

        DB::statement("ALTER TABLE payslips ADD CONSTRAINT chk_payslips_expense_posting_status CHECK (expense_posting_status IN ('pending','posted'))");

        DB::statement("ALTER TABLE companies ADD CONSTRAINT chk_companies_business_type CHECK (business_type IN ('physical_goods','digital_product','hybrid','project_services','shared_overhead'))");

        DB::statement("ALTER TABLE monthly_attendance_summaries ADD CONSTRAINT chk_monthly_attendance_summaries_period_month CHECK (period_month REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE employees DROP CHECK chk_employees_employment_status');
        DB::statement('ALTER TABLE employees DROP CHECK chk_employees_contract_type');
        DB::statement('ALTER TABLE attendances DROP CHECK chk_attendances_recorded_by');
        DB::statement('ALTER TABLE leaves DROP CHECK chk_leaves_leave_type');
        DB::statement('ALTER TABLE leaves DROP CHECK chk_leaves_leave_status');
        DB::statement('ALTER TABLE payroll_runs DROP CHECK chk_payroll_runs_payroll_status');
        DB::statement('ALTER TABLE payroll_runs DROP CHECK chk_payroll_runs_period_month');
        DB::statement('ALTER TABLE payslips DROP CHECK chk_payslips_expense_posting_status');
        DB::statement('ALTER TABLE companies DROP CHECK chk_companies_business_type');
        DB::statement('ALTER TABLE monthly_attendance_summaries DROP CHECK chk_monthly_attendance_summaries_period_month');

        DB::statement('ALTER TABLE employees MODIFY national_id VARCHAR(10) NOT NULL');
    }
};
