-- =====================================================================
-- ERP آرشامان — اسکیمای ماژول ۱: احراز هویت و دسترسی (Core/Auth)
-- MySQL 8.0+
-- توجه: UUID و enum در سطح Laravel مدیریت می‌شوند (نه دیتابیس) برای انعطاف بیشتر.
-- نام‌گذاری ستون‌ها طبق docs/DATABASE_CONVENTIONS.md — هر FK نقش کامل خودش را حمل می‌کند.
-- این فایل مرجع ساختار است؛ در عمل migration های Laravel ساخته می‌شوند.
-- =====================================================================

-- =====================================================================
-- جدول ۱: companies — شرکت‌ها (پایه چندشرکتی)
-- business_type: به جای ENUM دیتابیس، VARCHAR + کنترل در Laraval (قابل‌توسعه‌تر)
-- مقادیر مجاز: physical_goods, digital_product, hybrid, project_services, shared_overhead
-- =====================================================================
CREATE TABLE companies (
    id                 CHAR(36)     NOT NULL PRIMARY KEY,   -- UUID توسط Laravel (HasUuids)
    name               VARCHAR(200) NOT NULL,
    slug               VARCHAR(50)  NOT NULL,               -- verifex, doano, ... تغییرناپذیر
    business_type      VARCHAR(30)  NOT NULL,               -- کنترل مقادیر در Laravel (enum PHP)
    base_currency      VARCHAR(3)   NOT NULL DEFAULT 'IRR',
    woocommerce_config JSON         NULL,                   -- {url, key, secret} رمزنگاری‌شده در لایه اپ
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_by_user_id CHAR(36)     NULL,                   -- کدام کاربر این شرکت را ثبت کرد
    updated_by_user_id CHAR(36)     NULL,
    created_at         TIMESTAMP    NULL,
    updated_at         TIMESTAMP    NULL,
    deleted_at         TIMESTAMP    NULL,                   -- soft delete

    UNIQUE KEY uq_companies_slug (slug),
    KEY idx_companies_active (is_active),
    CONSTRAINT fk_companies_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_companies_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- نکته اجرایی: چون companies و users به هم وابسته‌اند (companies به users ارجاع می‌دهد
-- برای created_by_user_id/updated_by_user_id)، هنگام migration واقعی این دو قید FK را
-- در یک migration جدا و بعد از ساخت هر دو جدول اضافه کن (یا nullable + بدون قید در ساخت اول،
-- قید در migration دوم) تا خطای وابستگی دوری (circular dependency) در MySQL رخ ندهد.


-- =====================================================================
-- جدول ۲: users — کاربران داخلی (نه مشتری‌ها)
-- =====================================================================
CREATE TABLE users (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    full_name           VARCHAR(200) NOT NULL,
    email               VARCHAR(200) NOT NULL,
    password            VARCHAR(255) NOT NULL,                -- نام استاندارد Laravel (bcrypt/argon2)
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    is_super_admin      TINYINT(1)   NOT NULL DEFAULT 0,      -- ادمین کل: دسترسی همه شرکت‌ها
    last_login_at       TIMESTAMP    NULL,
    email_verified_at   TIMESTAMP    NULL,
    remember_token      VARCHAR(100) NULL,
    created_by_user_id  CHAR(36)     NULL,                    -- کدام ادمین این کاربر را ساخت (خالی برای اولین ادمین کل)
    updated_by_user_id  CHAR(36)     NULL,
    created_at          TIMESTAMP    NULL,
    updated_at          TIMESTAMP    NULL,
    deleted_at          TIMESTAMP    NULL,

    UNIQUE KEY uq_users_email (email),
    KEY idx_users_active (is_active),
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۳: roles — نقش‌ها
-- =====================================================================
CREATE TABLE roles (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,                     -- holding_admin, accountant, ...
    display_name VARCHAR(150) NOT NULL,
    description  TEXT         NULL,
    is_system    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   TIMESTAMP    NULL,
    updated_at   TIMESTAMP    NULL,

    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۴: permissions — مجوزها (ریزدانه)
-- =====================================================================
CREATE TABLE permissions (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,                     -- orders.view, expenses.approve
    module       VARCHAR(50)  NOT NULL,
    display_name VARCHAR(200) NOT NULL,
    created_at   TIMESTAMP    NULL,

    UNIQUE KEY uq_permissions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۵: role_permission — واسط نقش و مجوز
-- =====================================================================
CREATE TABLE role_permission (
    role_id       CHAR(36) NOT NULL,
    permission_id CHAR(36) NOT NULL,

    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۶: user_company_roles — قلب دسترسی دوبعدی نقش×شرکت
-- «کاربر X در شرکت Y نقش Z دارد»
-- =====================================================================
CREATE TABLE user_company_roles (
    id                  CHAR(36) NOT NULL PRIMARY KEY,
    user_id             CHAR(36) NOT NULL,                  -- کاربری که این نقش را دارد
    owner_company_id    CHAR(36) NOT NULL,                  -- شرکتی که این نقش در آن معتبر است
    assigned_role_id    CHAR(36) NOT NULL,                  -- نقش تخصیص‌داده‌شده
    created_by_user_id  CHAR(36) NULL,                      -- کدام ادمین این تخصیص را ثبت کرد
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    UNIQUE KEY uq_user_company (user_id, owner_company_id),      -- یک نقش در هر شرکت
    KEY idx_ucr_user (user_id),
    KEY idx_ucr_company (owner_company_id),
    CONSTRAINT fk_ucr_user       FOREIGN KEY (user_id)          REFERENCES users(id)     ON DELETE CASCADE,
    CONSTRAINT fk_ucr_company    FOREIGN KEY (owner_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_ucr_role       FOREIGN KEY (assigned_role_id) REFERENCES roles(id)     ON DELETE RESTRICT,
    CONSTRAINT fk_ucr_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۷: user_invitations — دعوت کاربر جدید (جایگزین ثبت‌نام عمومی)
-- =====================================================================
CREATE TABLE user_invitations (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    email               VARCHAR(200) NOT NULL,
    full_name           VARCHAR(200) NOT NULL,
    token               VARCHAR(64)  NOT NULL,
    owner_company_id    CHAR(36)     NULL,                  -- شرکتی که دعوت برای آن است
    assigned_role_id    CHAR(36)     NULL,                  -- نقشی که بعد از قبول دعوت داده می‌شود
    invited_by_user_id  CHAR(36)     NOT NULL,               -- کدام ادمین دعوت کرد
    accepted_at         TIMESTAMP    NULL,
    expires_at          TIMESTAMP    NOT NULL,
    created_at          TIMESTAMP    NULL,

    UNIQUE KEY uq_invitation_token (token),
    KEY idx_invitations_email (email),
    CONSTRAINT fk_inv_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_role       FOREIGN KEY (assigned_role_id)   REFERENCES roles(id)     ON DELETE SET NULL,
    CONSTRAINT fk_inv_invited_by FOREIGN KEY (invited_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۸: sessions — نشست‌ها (استاندارد Laravel — نام ستون‌ها native فریم‌ورک،
-- طبق بخش ۱۰ DATABASE_CONVENTIONS.md عوض نمی‌شوند، شامل شرکت فعال)
-- =====================================================================
CREATE TABLE sessions (
    id                     VARCHAR(255) NOT NULL PRIMARY KEY,
    user_id                CHAR(36)     NULL,                -- ستون استاندارد Laravel، دست‌نخورده
    active_company_id      CHAR(36)     NULL,                -- شرکت فعال در سوییچر همین session
    ip_address             VARCHAR(45)  NULL,
    user_agent             TEXT         NULL,
    payload                LONGTEXT     NOT NULL,
    last_activity          INT          NOT NULL,

    KEY idx_sessions_user (user_id),
    KEY idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۹: activity_log — رد ممیزی (audit trail، ساختار پکیج Spatie —
-- نام ستون‌ها native پکیج، طبق بخش ۱۰ DATABASE_CONVENTIONS.md عوض نمی‌شوند)
-- =====================================================================
CREATE TABLE activity_log (
    id                CHAR(36)     NOT NULL PRIMARY KEY,
    log_name          VARCHAR(100) NULL,
    description       TEXT         NOT NULL,
    subject_type      VARCHAR(200) NULL,
    subject_id        CHAR(36)     NULL,
    causer_id         CHAR(36)     NULL,                     -- ستون استاندارد Spatie، دست‌نخورده
    owner_company_id  CHAR(36)     NULL,                     -- شرکتی که این رویداد در آن رخ داد
    event             VARCHAR(50)  NULL,
    properties        JSON         NULL,                     -- مقادیر قبل/بعد
    created_at        TIMESTAMP    NULL,

    KEY idx_activity_subject (subject_type, subject_id),
    KEY idx_activity_causer (causer_id),
    KEY idx_activity_company (owner_company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- داده اولیه (seed) — از طریق Seeder های Laravel اجرا می‌شوند.
-- در MySQL برای UUID از UUID() یا (بهتر) از HasUuids لاراول استفاده کن.
-- مقادیر نمونه:
-- companies: آرشامان(project_services), وریفکس(hybrid), تی‌کارت(physical_goods),
--            دعانو(physical_goods), پیکسنتری(digital_product), ستاد مشترک(shared_overhead)
-- roles:     holding_admin, accountant, operator, viewer
-- ادمین کل با: php artisan erp:create-admin
-- =====================================================================

-- =====================================================================
-- تفاوت‌های کلیدی با نسخه PostgreSQL (برای مرجع):
-- 1. UUID: CHAR(36) + تولید در Laravel (HasUuids)، نه gen_random_uuid()
-- 2. business_type: VARCHAR + PHP enum، نه ENUM دیتابیس (قابل‌توسعه‌تر)
-- 3. JSONB → JSON
-- 4. BOOLEAN → TINYINT(1)
-- 5. TIMESTAMPTZ → TIMESTAMP (Laravel با UTC مدیریت می‌کند)
-- 6. ایندکس‌های شرطی (WHERE) → ایندکس معمولی
-- 7. نام‌گذاری ستون‌ها طبق docs/DATABASE_CONVENTIONS.md: company_id → owner_company_id،
--    created_by/updated_by → created_by_user_id/updated_by_user_id، invited_by → invited_by_user_id
-- =====================================================================
