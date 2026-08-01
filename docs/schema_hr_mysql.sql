-- =====================================================================
-- ERP آرشامان — اسکیمای ماژول ۲: منابع انسانی (HR)
-- MySQL 8.0+
-- نام‌گذاری ستون‌ها طبق docs/DATABASE_CONVENTIONS.md — هر FK نقش کامل خودش را حمل می‌کند.
-- این فایل مرجع ساختار است؛ در عمل migration های Laravel ساخته می‌شوند.
-- =====================================================================

-- =====================================================================
-- جدول ۱: employees — پرونده پرسنلی (مستقل از users)
-- employment_status: active | on_leave | terminated
-- contract_type: permanent | temporary | project_based
-- کنترل مقادیر enum در لایه Laravel (PHP enum)، نه ENUM دیتابیس.
-- =====================================================================
CREATE TABLE employees (
    id                   CHAR(36)      NOT NULL PRIMARY KEY,
    owner_company_id     CHAR(36)      NOT NULL,               -- شرکت محل خدمت
    user_id              CHAR(36)      NULL,                   -- فقط اگر کارمند پنل خودش را دارد
    full_name            VARCHAR(200)  NOT NULL,
    national_id          VARCHAR(10)   NOT NULL,
    phone                VARCHAR(20)   NULL,
    address              TEXT          NULL,
    position             VARCHAR(150)  NOT NULL,
    hire_date            DATE          NOT NULL,
    termination_date     DATE          NULL,
    employment_status    VARCHAR(20)   NOT NULL DEFAULT 'active',
    contract_type        VARCHAR(20)   NOT NULL,
    contract_start_date  DATE          NOT NULL,
    contract_end_date    DATE          NULL,                   -- خالی = قرارداد دائم
    base_salary          DECIMAL(18,2) NOT NULL,
    currency_id          CHAR(36)      NULL,                   -- بدون FK فعلاً؛ ماژول Currency هنوز نیست
    created_by_user_id   CHAR(36)      NULL,
    updated_by_user_id   CHAR(36)      NULL,
    created_at           TIMESTAMP     NULL,
    updated_at           TIMESTAMP     NULL,
    deleted_at           TIMESTAMP     NULL,

    UNIQUE KEY uq_employees_company_national_id (owner_company_id, national_id),
    KEY idx_employees_company_status (owner_company_id, employment_status),
    KEY idx_employees_user (user_id),
    CONSTRAINT fk_employees_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_employees_user       FOREIGN KEY (user_id)            REFERENCES users(id),
    CONSTRAINT fk_employees_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_employees_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۲: holidays — تعطیلات رسمی و تقویم کاری
-- owner_company_id خالی = تعطیلی سراسری (همه شرکت‌ها)
-- =====================================================================
CREATE TABLE holidays (
    id                   CHAR(36)      NOT NULL PRIMARY KEY,
    owner_company_id     CHAR(36)      NULL,                   -- NULL = سراسری
    title                VARCHAR(150)  NOT NULL,
    holiday_date         DATE          NOT NULL,
    is_recurring_yearly  TINYINT(1)    NOT NULL DEFAULT 0,
    created_by_user_id   CHAR(36)      NULL,
    created_at           TIMESTAMP     NULL,

    KEY idx_holidays_date (holiday_date),
    KEY idx_holidays_company (owner_company_id),
    CONSTRAINT fk_holidays_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_holidays_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۳: attendances — ثبت تردد (ورود/خروج)
-- recorded_by: self (خودِ کارمند از پنل شخصی) | admin (ثبت دستی ادمین/حسابدار)
--
-- ⚠️ انحراف مستند از نسخه اول (Session 6.6):
-- هر ردیف یک **تردد** است (یک ورود و یک خروج)، نه یک روز. کارمند می‌تواند در
-- یک روز چند بار ورود/خروج بزند (رفتن برای کار شخصی و برگشتن)، پس:
--   ۱. UNIQUE(employee_id, attendance_date) برداشته شد.
--   ۲. late_minutes و overtime_minutes از این جدول حذف شدند — مفهومشان
--      **روزانه** است نه ردیفی. با دو تردد چهارساعته در یک روز، هر ردیف جدا
--      با ۴۸۰ دقیقه مقایسه می‌شد و روزِ کاملاً کارشده ۴۸۰ دقیقه کسری می‌گرفت.
--      حالا AttendanceCalculator در سطح روز محاسبه می‌کند و نتیجه فقط در
--      monthly_attendance_summaries ذخیره می‌شود.
--   ۳. ستون تولیدشده open_punch_marker + UNIQUE(employee_id, open_punch_marker)
--      تضمین می‌کند هر کارمند حداکثر یک تردد **باز** دارد. چون NULL ها در
--      ایندکس یکتا با هم برخورد نمی‌کنند، ردیف‌های بسته آزادند.
-- migration مرجع: 2026_07_29_100013_convert_attendances_to_punch_pairs.php
-- =====================================================================
CREATE TABLE attendances (
    id                   CHAR(36)      NOT NULL PRIMARY KEY,
    employee_id          CHAR(36)      NOT NULL,
    owner_company_id     CHAR(36)      NOT NULL,
    attendance_date      DATE          NOT NULL,
    check_in_at          TIMESTAMP     NULL,
    check_out_at         TIMESTAMP     NULL,   -- NULL = تردد باز (هنوز خروج نزده)
    -- ستون تولیدشده: فقط برای ردیف‌های باز مقدار می‌گیرد.
    open_punch_marker    INT GENERATED ALWAYS AS (CASE WHEN check_out_at IS NULL THEN 1 ELSE NULL END) VIRTUAL,
    -- «چه کسی اولین بار ثبت کرد» — با ویرایش عوض نمی‌شود، وگرنه ویرایش ادمین
    -- روی یک رکورد self آن را به admin برمی‌گرداند و تفکیکی که این ستون برای
    -- آن ساخته شده از بین می‌رود.
    recorded_by          VARCHAR(10)   NOT NULL DEFAULT 'admin', -- self | admin
    created_by_user_id   CHAR(36)      NULL,
    -- ← افزوده در Session 6+: «چه کسی آخرین بار ویرایش کرد».
    -- migration مرجع: 2026_07_29_100009_add_updated_by_user_id_to_attendances_table.php
    updated_by_user_id   CHAR(36)      NULL,
    created_at           TIMESTAMP     NULL,
    updated_at           TIMESTAMP     NULL,

    -- هر کارمند حداکثر یک تردد باز — تنها راه گرفتن این تضمین در MySQL 8 که
    -- ایندکس یکتای شرطی ندارد.
    UNIQUE KEY uq_attendance_single_open_punch (employee_id, open_punch_marker),
    KEY idx_attendance_company_date (owner_company_id, attendance_date),
    KEY idx_attendance_employee_open (employee_id, check_out_at),
    CONSTRAINT fk_attendance_employee   FOREIGN KEY (employee_id)        REFERENCES employees(id),
    CONSTRAINT fk_attendance_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_attendance_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_attendance_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۴: monthly_attendance_summaries — جمع ماهانه کارکرد و غیبت
-- period_month: فرمت شمسی مثل '1405-04'
-- قابل محاسبه دوباره (idempotent) — رکورد قبلی همان ماه/کارمند جایگزین می‌شود
-- =====================================================================
CREATE TABLE monthly_attendance_summaries (
    id                     CHAR(36)  NOT NULL PRIMARY KEY,
    employee_id            CHAR(36)  NOT NULL,
    owner_company_id       CHAR(36)  NOT NULL,
    period_month           VARCHAR(7) NOT NULL,               -- '1405-04'
    total_worked_days      INT       NOT NULL DEFAULT 0,
    total_absent_days      INT       NOT NULL DEFAULT 0,
    total_late_minutes     INT       NOT NULL DEFAULT 0,
    total_overtime_minutes INT       NOT NULL DEFAULT 0,
    total_leave_days       INT       NOT NULL DEFAULT 0,      -- مرخصی‌های approved همان ماه
    calculated_at          TIMESTAMP NULL,

    UNIQUE KEY uq_monthly_summary_employee_period (employee_id, period_month),
    KEY idx_monthly_summary_company_period (owner_company_id, period_month),
    CONSTRAINT fk_monthly_summary_employee FOREIGN KEY (employee_id)      REFERENCES employees(id),
    CONSTRAINT fk_monthly_summary_company  FOREIGN KEY (owner_company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۵: leaves — درخواست مرخصی + گردش تأیید
-- leave_type: annual | sick | unpaid
-- leave_status: pending | approved | rejected
-- =====================================================================
CREATE TABLE leaves (
    id                   CHAR(36)      NOT NULL PRIMARY KEY,
    employee_id          CHAR(36)      NOT NULL,
    owner_company_id     CHAR(36)      NOT NULL,
    leave_type           VARCHAR(20)   NOT NULL,
    start_date           DATE          NOT NULL,
    end_date             DATE          NOT NULL,
    days_count           INT           NOT NULL,               -- بدون جمعه/تعطیل، از WorkCalendar
    leave_status         VARCHAR(20)   NOT NULL DEFAULT 'pending',
    reason               TEXT          NULL,                   -- دلیل خودِ درخواست (نوشته کارمند)
    -- ← افزوده در Session 6+: دلیل تصمیم مدیر هنگام رد. عمداً ستون جدا از
    -- reason است چون دو نقش متفاوت‌اند و هر دو باید هم‌زمان قابل مشاهده بمانند.
    -- migration مرجع: 2026_07_29_100010_add_rejection_reason_to_leaves_table.php
    rejection_reason     TEXT          NULL,
    approved_by_user_id  CHAR(36)      NULL,                   -- تا تأیید، خالی
    created_by_user_id   CHAR(36)      NULL,
    created_at           TIMESTAMP     NULL,
    updated_at           TIMESTAMP     NULL,

    KEY idx_leaves_employee_status (employee_id, leave_status),
    KEY idx_leaves_company_dates (owner_company_id, start_date, end_date),
    CONSTRAINT fk_leaves_employee    FOREIGN KEY (employee_id)         REFERENCES employees(id),
    CONSTRAINT fk_leaves_company     FOREIGN KEY (owner_company_id)    REFERENCES companies(id),
    CONSTRAINT fk_leaves_approved_by FOREIGN KEY (approved_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_leaves_created_by  FOREIGN KEY (created_by_user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۶: payroll_runs — یک دوره محاسبه حقوق (یک شرکت، یک ماه)
-- payroll_status: draft | calculated | finalized
-- بعد از finalized، هیچ payslip این run قابل ویرایش نیست (قفل مالی).
-- =====================================================================
CREATE TABLE payroll_runs (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    owner_company_id      CHAR(36)     NOT NULL,
    period_month          VARCHAR(7)   NOT NULL,               -- '1405-04'
    payroll_status        VARCHAR(20)  NOT NULL DEFAULT 'draft',
    calculated_at         TIMESTAMP    NULL,
    calculated_by_user_id CHAR(36)     NULL,
    finalized_at          TIMESTAMP    NULL,
    finalized_by_user_id  CHAR(36)     NULL,
    created_at            TIMESTAMP    NULL,
    updated_at            TIMESTAMP    NULL,

    UNIQUE KEY uq_payroll_run_company_period (owner_company_id, period_month),
    CONSTRAINT fk_payroll_run_company       FOREIGN KEY (owner_company_id)      REFERENCES companies(id),
    CONSTRAINT fk_payroll_run_calculated_by FOREIGN KEY (calculated_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_payroll_run_finalized_by  FOREIGN KEY (finalized_by_user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۷: payslips — فیش حقوقی هر کارمند در یک payroll_run
-- تمام مبالغ snapshot هستند (کپی لحظه محاسبه، نه reference زنده به employees.base_salary)
-- expense_posting_status همیشه 'pending' می‌ماند در این فاز — طبق BACKLOG.md #1
--
-- ⚠️ انحراف مستند از نسخه اول این فایل (تصمیم A، Session 6):
-- نسخه اول ستون owner_company_id نداشت و شرکت را فقط از راه payroll_run استنتاج
-- می‌کرد. این با CLAUDE.md بند ۵.۱ در تضاد بود («هر مدل عملیاتی بدون
-- BelongsToCompany یک باگ امنیتی است»). ستون مستقیم اضافه شد تا Global Scope
-- یک‌لایه و بدون join کار کند و گزارش هزینه پرسنل (Session 7) هم به join نیاز
-- نداشته باشد. migration مرجع: 2026_07_29_100007_create_payslips_table.php
--
-- ⚠️ فرمول‌های insurance_amount، tax_amount و مخرج نرخ روزانه موقت‌اند و نیازمند
-- تأیید حسابدار واقعی کارفرما — پارامترها در config/payroll.php.
-- =====================================================================
CREATE TABLE payslips (
    id                          CHAR(36)      NOT NULL PRIMARY KEY,
    payroll_run_id              CHAR(36)      NOT NULL,
    employee_id                 CHAR(36)      NOT NULL,
    owner_company_id            CHAR(36)      NOT NULL,        -- ← افزوده در Session 6 (تصمیم A)
    gross_salary_amount         DECIMAL(18,2) NOT NULL,        -- snapshot از employees.base_salary
    overtime_amount              DECIMAL(18,2) NOT NULL DEFAULT 0,
    absence_deduction_amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    unpaid_leave_deduction_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    insurance_amount             DECIMAL(18,2) NOT NULL DEFAULT 0, -- فرمول ساده موقت
    tax_amount                   DECIMAL(18,2) NOT NULL DEFAULT 0, -- فرمول ساده موقت
    benefits_amount               DECIMAL(18,2) NOT NULL DEFAULT 0,
    net_amount                   DECIMAL(18,2) NOT NULL,        -- gross+overtime+benefits-deductions-insurance-tax، clamp‌شده در صفر
    -- ← افزوده در Session 6: مبلغ خام محاسبه‌شده، فقط وقتی منفی درآمد.
    -- NULL = هیچ clamp ای رخ نداده. پرشدنش یعنی کسورات از حقوق و مزایا بیشتر
    -- شده و فیش نیاز به بررسی دستی حسابدار دارد (در UI هشدار داده می‌شود).
    -- migration مرجع: 2026_07_29_100008_add_raw_net_amount_to_payslips_table.php
    raw_net_amount               DECIMAL(18,2) NULL,
    currency_id                  CHAR(36)      NULL,
    expense_posting_status       VARCHAR(20)   NOT NULL DEFAULT 'pending', -- pending | posted
    created_at                   TIMESTAMP     NULL,
    updated_at                   TIMESTAMP     NULL,

    UNIQUE KEY uq_payslip_run_employee (payroll_run_id, employee_id),
    KEY idx_payslip_expense_status (expense_posting_status),
    KEY idx_payslip_company_employee (owner_company_id, employee_id),
    CONSTRAINT fk_payslip_run      FOREIGN KEY (payroll_run_id)  REFERENCES payroll_runs(id),
    CONSTRAINT fk_payslip_employee FOREIGN KEY (employee_id)     REFERENCES employees(id),
    CONSTRAINT fk_payslip_company  FOREIGN KEY (owner_company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- داده اولیه (seed):
-- holidays: چند تعطیلی رسمی نمونه ایران (نوروز و غیره)
-- بقیه جدول‌ها seeder نمی‌خواهند — از طریق Livewire/Action ساخته می‌شوند.
-- =====================================================================

-- =====================================================================
-- یادآوری قرارداد نام‌گذاری (docs/DATABASE_CONVENTIONS.md):
-- 1. owner_company_id به‌جای company_id خام — همه‌جا.
-- 2. created_by_user_id/updated_by_user_id به‌جای created_by/updated_by خام.
-- 3. approved_by_user_id در leaves — نقش کامل، نه user_id خام
--    (چون employee که مرخصی گرفته و کاربری که تأیید کرده دو نقش متفاوتند).
-- 4. enum های کسب‌وکاری (employment_status, contract_type, leave_type,
--    leave_status, payroll_status, recorded_by, expense_posting_status)
--    VARCHAR در دیتابیس + enum PHP در لایه اپ.
-- 5. هر FK ایندکس دارد (خودکار توسط constraint در InnoDB).
-- =====================================================================
