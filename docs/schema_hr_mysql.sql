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
-- جدول ۳: attendances — ثبت ورود/خروج روزانه
-- recorded_by: self (خودِ کارمند از پنل شخصی) | admin (ثبت دستی ادمین/حسابدار)
-- =====================================================================
CREATE TABLE attendances (
    id                   CHAR(36)      NOT NULL PRIMARY KEY,
    employee_id          CHAR(36)      NOT NULL,
    owner_company_id     CHAR(36)      NOT NULL,
    attendance_date      DATE          NOT NULL,
    check_in_at          TIMESTAMP     NULL,
    check_out_at         TIMESTAMP     NULL,
    late_minutes         INT           NOT NULL DEFAULT 0,
    overtime_minutes     INT           NOT NULL DEFAULT 0,
    recorded_by          VARCHAR(10)   NOT NULL DEFAULT 'admin', -- self | admin
    created_by_user_id   CHAR(36)      NULL,
    created_at           TIMESTAMP     NULL,
    updated_at           TIMESTAMP     NULL,

    UNIQUE KEY uq_attendance_employee_date (employee_id, attendance_date),
    KEY idx_attendance_company_date (owner_company_id, attendance_date),
    CONSTRAINT fk_attendance_employee   FOREIGN KEY (employee_id)        REFERENCES employees(id),
    CONSTRAINT fk_attendance_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_attendance_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
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
    reason               TEXT          NULL,
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
-- =====================================================================
CREATE TABLE payslips (
    id                          CHAR(36)      NOT NULL PRIMARY KEY,
    payroll_run_id              CHAR(36)      NOT NULL,
    employee_id                 CHAR(36)      NOT NULL,
    gross_salary_amount         DECIMAL(18,2) NOT NULL,        -- snapshot از employees.base_salary
    overtime_amount              DECIMAL(18,2) NOT NULL DEFAULT 0,
    absence_deduction_amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    unpaid_leave_deduction_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    insurance_amount             DECIMAL(18,2) NOT NULL DEFAULT 0, -- فرمول ساده موقت
    tax_amount                   DECIMAL(18,2) NOT NULL DEFAULT 0, -- فرمول ساده موقت
    benefits_amount               DECIMAL(18,2) NOT NULL DEFAULT 0,
    net_amount                   DECIMAL(18,2) NOT NULL,        -- gross+overtime+benefits-deductions-insurance-tax
    currency_id                  CHAR(36)      NULL,
    expense_posting_status       VARCHAR(20)   NOT NULL DEFAULT 'pending', -- pending | posted
    created_at                   TIMESTAMP     NULL,
    updated_at                   TIMESTAMP     NULL,

    UNIQUE KEY uq_payslip_run_employee (payroll_run_id, employee_id),
    KEY idx_payslip_expense_status (expense_posting_status),
    CONSTRAINT fk_payslip_run      FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id),
    CONSTRAINT fk_payslip_employee FOREIGN KEY (employee_id)    REFERENCES employees(id)
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
