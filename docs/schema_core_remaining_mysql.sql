-- =====================================================================
-- ERP آرشامان — اسکیمای تکمیل هسته: طرف‌حساب، ارز، سال مالی
-- MySQL 8.0+
-- نام‌گذاری ستون‌ها طبق docs/DATABASE_CONVENTIONS.md
-- این فایل مرجع ساختار است؛ در عمل migration های Laravel ساخته می‌شوند.
-- =====================================================================

-- =====================================================================
-- جدول ۱: parties — طرف‌حساب (مشتری/تأمین‌کننده)
-- party_type: individual | company
-- قید حیاتی: حداقل یکی از is_customer/is_supplier باید true باشد
-- =====================================================================
CREATE TABLE parties (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    owner_company_id    CHAR(36)      NOT NULL,
    name                VARCHAR(200)  NOT NULL,
    party_type          VARCHAR(20)   NOT NULL DEFAULT 'individual', -- individual | company
    is_customer         TINYINT(1)    NOT NULL DEFAULT 0,
    is_supplier         TINYINT(1)    NOT NULL DEFAULT 0,
    phone               VARCHAR(20)   NULL,
    email               VARCHAR(200)  NULL,
    economic_code       VARCHAR(30)   NULL,               -- کد اقتصادی، فقط اشخاص حقوقی
    address             TEXT          NULL,
    created_by_user_id  CHAR(36)      NULL,
    updated_by_user_id  CHAR(36)      NULL,
    created_at          TIMESTAMP     NULL,
    updated_at          TIMESTAMP     NULL,
    deleted_at          TIMESTAMP     NULL,

    KEY idx_parties_company (owner_company_id),
    KEY idx_parties_customer (owner_company_id, is_customer),
    KEY idx_parties_supplier (owner_company_id, is_supplier),
    CONSTRAINT fk_parties_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_parties_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_parties_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id),
    CONSTRAINT chk_parties_role CHECK (is_customer = 1 OR is_supplier = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۲: currencies — ارزهای پشتیبانی‌شده (غیر از تومان)
-- تومان ارز پایه سیستم است و در این جدول رکورد ندارد — نرخ‌ها همیشه
-- «هر واحد ارز چند تومان» را نگه می‌دارند.
-- =====================================================================
CREATE TABLE currencies (
    id          CHAR(36)     NOT NULL PRIMARY KEY,
    code        VARCHAR(3)   NOT NULL,                    -- USD, EUR, AED
    name        VARCHAR(100) NOT NULL,
    symbol      VARCHAR(10)  NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NULL,

    UNIQUE KEY uq_currencies_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۳: exchange_rates — نرخ روزانه هر ارز به تومان
-- resolve با «تاریخ دقیق یا آخرین نرخ قبل از آن» طبق ExchangeRateResolver
-- =====================================================================
CREATE TABLE exchange_rates (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    currency_id         CHAR(36)      NOT NULL,
    rate_to_toman       DECIMAL(18,2) NOT NULL,            -- هر ۱ واحد ارز = چند تومان
    effective_date      DATE          NOT NULL,
    created_by_user_id  CHAR(36)      NULL,
    created_at          TIMESTAMP     NULL,

    UNIQUE KEY uq_exchange_rate_currency_date (currency_id, effective_date),
    KEY idx_exchange_rate_date (effective_date),
    CONSTRAINT fk_exchange_rate_currency   FOREIGN KEY (currency_id)        REFERENCES currencies(id),
    CONSTRAINT fk_exchange_rate_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۴: fiscal_periods — سال‌های مالی شمسی (اول فروردین تا آخر اسفند)
-- منطق کامل بستن دوره و انتقال مانده در فاز ۶ (حسابداری) اضافه می‌شود؛
-- اینجا فقط محدوده و قفل ساختاری.
-- =====================================================================
CREATE TABLE fiscal_periods (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    owner_company_id      CHAR(36)     NOT NULL,
    name                  VARCHAR(100) NOT NULL,           -- "سال مالی ۱۴۰۵"
    start_date            DATE         NOT NULL,           -- میلادی معادل اول فروردین
    end_date               DATE         NOT NULL,          -- میلادی معادل آخر اسفند
    is_closed              TINYINT(1)   NOT NULL DEFAULT 0,
    closed_at              TIMESTAMP    NULL,
    closed_by_user_id      CHAR(36)     NULL,
    created_at              TIMESTAMP    NULL,

    UNIQUE KEY uq_fiscal_period_company_name (owner_company_id, name),
    KEY idx_fiscal_period_company_dates (owner_company_id, start_date, end_date),
    CONSTRAINT fk_fiscal_period_company   FOREIGN KEY (owner_company_id)    REFERENCES companies(id),
    CONSTRAINT fk_fiscal_period_closed_by FOREIGN KEY (closed_by_user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- داده اولیه (seed):
-- currencies: USD (دلار آمریکا)، EUR (یورو)، AED (درهم امارات) — نمونه
-- fiscal_periods: سال مالی جاری برای هر شش شرکت، بر پایه تاریخ اجرای seeder
-- =====================================================================

-- =====================================================================
-- یادآوری قرارداد نام‌گذاری:
-- 1. owner_company_id همه‌جا (نه company_id خام).
-- 2. created_by_user_id/updated_by_user_id (نه خام).
-- 3. closed_by_user_id در fiscal_periods — نقش کامل، چون می‌تواند با
--    created_by متفاوت باشد (کسی که سال مالی ساخت با کسی که بستش یکی نیست).
-- 4. enum های کسب‌وکاری (party_type) VARCHAR + enum PHP.
-- 5. CHECK constraint سطح دیتابیس برای parties — دومین‌لایه دفاعی،
--    مکمل Rule::unique/validation سطح Laravel، طبق الگوی تأییدشده در
--    ماژول HR (employees.national_id).
-- =====================================================================
