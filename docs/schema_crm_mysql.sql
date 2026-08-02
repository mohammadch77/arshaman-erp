-- =====================================================================
-- ERP آرشامان — اسکیمای گروه و: مدیریت ارتباط با مشتری (CRM)
-- MySQL 8.0+
-- نام‌گذاری ستون‌ها طبق docs/DATABASE_CONVENTIONS.md
-- این فایل مرجع ساختار است؛ در عمل migration های Laravel ساخته می‌شوند.
-- =====================================================================

-- =====================================================================
-- جدول ۱: contacts — پروفایل واحد هلدینگی (Golden Record)
-- ⚠️ عمداً owner_company_id ندارد — بین‌شرکتی است، مثل holidays در HR.
-- هرگز BelongsToCompany روی مدل این جدول نگذار.
-- =====================================================================
CREATE TABLE contacts (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    full_name           VARCHAR(200)  NOT NULL,
    phone               VARCHAR(20)   NOT NULL,
    email               VARCHAR(200)  NULL,
    created_by_user_id  CHAR(36)      NULL,
    updated_by_user_id  CHAR(36)      NULL,
    created_at          TIMESTAMP     NULL,
    updated_at          TIMESTAMP     NULL,
    deleted_at          TIMESTAMP     NULL,

    KEY idx_contacts_phone (phone),
    KEY idx_contacts_email (email),
    CONSTRAINT fk_contacts_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_contacts_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۲: contact_site_profiles — پروفایل مخاطب در یک شرکت مشخص
-- party_id اختیاری: لینک به طرف‌حساب مالی (Party ≠ Contact، طبق نکات حیاتی سند)
-- =====================================================================
CREATE TABLE contact_site_profiles (
    id                     CHAR(36)      NOT NULL PRIMARY KEY,
    contact_id             CHAR(36)      NOT NULL,           -- Golden Record هلدینگی
    owner_company_id       CHAR(36)      NOT NULL,
    party_id               CHAR(36)      NULL,               -- لینک اختیاری به طرف‌حساب مالی
    site_full_name         VARCHAR(200)  NULL,                -- اگر نام محلی فرق داشت
    first_seen_at          TIMESTAMP     NULL,
    total_purchase_amount  DECIMAL(18,2) NOT NULL DEFAULT 0,   -- فعلاً صفر تا سفارش واقعی
    created_by_user_id     CHAR(36)      NULL,
    updated_by_user_id     CHAR(36)      NULL,
    created_at             TIMESTAMP     NULL,
    updated_at             TIMESTAMP     NULL,

    UNIQUE KEY uq_contact_site_profile (contact_id, owner_company_id),
    KEY idx_contact_site_profile_company (owner_company_id),
    KEY idx_contact_site_profile_party (party_id),
    CONSTRAINT fk_csp_contact       FOREIGN KEY (contact_id)         REFERENCES contacts(id),
    CONSTRAINT fk_csp_company       FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_csp_party         FOREIGN KEY (party_id)           REFERENCES parties(id),
    CONSTRAINT fk_csp_created_by    FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_csp_updated_by    FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۳: interactions — تایم‌لاین تعامل (تماس، تلگرام، فرم، خرید)
-- interaction_type: call | telegram | site_form | purchase
-- source_order_id بدون FK فعلاً — جدول orders هنوز نیست (فاز ۳)
-- =====================================================================
CREATE TABLE interactions (
    id                    CHAR(36)     NOT NULL PRIMARY KEY,
    contact_site_profile_id CHAR(36)   NOT NULL,
    owner_company_id      CHAR(36)     NOT NULL,
    interaction_type      VARCHAR(20)  NOT NULL,             -- call | telegram | site_form | purchase
    notes                 TEXT         NULL,
    source_order_id        CHAR(36)     NULL,                -- TODO: FK به orders در فاز ۳
    occurred_at            TIMESTAMP    NOT NULL,
    created_by_user_id     CHAR(36)     NULL,
    created_at             TIMESTAMP    NULL,

    KEY idx_interactions_profile_date (contact_site_profile_id, occurred_at),
    KEY idx_interactions_company (owner_company_id),
    CONSTRAINT fk_interactions_profile     FOREIGN KEY (contact_site_profile_id) REFERENCES contact_site_profiles(id),
    CONSTRAINT fk_interactions_company     FOREIGN KEY (owner_company_id)        REFERENCES companies(id),
    CONSTRAINT fk_interactions_created_by  FOREIGN KEY (created_by_user_id)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۴: leads — قیف فروش و سرنخ
-- pipeline_stage: new | contacted | qualified | proposal | won | lost
-- contract_id بدون FK فعلاً — فقط برای آرشامان معنا دارد، فاز ۵
-- =====================================================================
CREATE TABLE leads (
    id                       CHAR(36)      NOT NULL PRIMARY KEY,
    owner_company_id         CHAR(36)      NOT NULL,
    contact_site_profile_id  CHAR(36)      NULL,             -- سرنخ می‌تواند بدون مخاطب کامل باشد
    source                   VARCHAR(30)   NOT NULL,          -- instagram | website | telegram | referral | other
    pipeline_stage           VARCHAR(20)   NOT NULL DEFAULT 'new',
    assigned_to_user_id      CHAR(36)      NULL,
    estimated_value          DECIMAL(18,2) NULL,
    notes                    TEXT          NULL,
    contract_id              CHAR(36)      NULL,             -- TODO: اتصال قرارداد در فاز ۵ (فقط آرشامان)
    created_by_user_id       CHAR(36)      NULL,
    updated_by_user_id       CHAR(36)      NULL,
    created_at                TIMESTAMP     NULL,
    updated_at                TIMESTAMP     NULL,

    KEY idx_leads_company_stage (owner_company_id, pipeline_stage),
    KEY idx_leads_assigned (assigned_to_user_id),
    CONSTRAINT fk_leads_company       FOREIGN KEY (owner_company_id)        REFERENCES companies(id),
    CONSTRAINT fk_leads_profile       FOREIGN KEY (contact_site_profile_id) REFERENCES contact_site_profiles(id),
    CONSTRAINT fk_leads_assigned_to   FOREIGN KEY (assigned_to_user_id)     REFERENCES users(id),
    CONSTRAINT fk_leads_created_by    FOREIGN KEY (created_by_user_id)      REFERENCES users(id),
    CONSTRAINT fk_leads_updated_by    FOREIGN KEY (updated_by_user_id)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۵: rfm_segments — بخش‌بندی خودکار مشتری
-- segment: vip | at_risk | dormant | new
-- تا سفارش واقعی نباشد، recency/frequency/monetary می‌توانند NULL بمانند
-- =====================================================================
CREATE TABLE rfm_segments (
    id                       CHAR(36)      NOT NULL PRIMARY KEY,
    contact_site_profile_id  CHAR(36)      NOT NULL,
    owner_company_id         CHAR(36)      NOT NULL,
    recency_days             INT           NULL,
    frequency_count          INT           NULL,
    monetary_amount          DECIMAL(18,2) NULL,
    segment                  VARCHAR(20)   NOT NULL DEFAULT 'new',
    calculated_at             TIMESTAMP     NULL,

    UNIQUE KEY uq_rfm_profile (contact_site_profile_id),
    KEY idx_rfm_company_segment (owner_company_id, segment),
    CONSTRAINT fk_rfm_profile FOREIGN KEY (contact_site_profile_id) REFERENCES contact_site_profiles(id),
    CONSTRAINT fk_rfm_company FOREIGN KEY (owner_company_id)        REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۶: campaigns — تعریف کمپین (قالب پیام + trigger)
-- trigger_type: winback_90days | shipping_notification | cross_sell | welcome_first_purchase
-- channel: telegram | sms
-- =====================================================================
CREATE TABLE campaigns (
    id                  CHAR(36)     NOT NULL PRIMARY KEY,
    owner_company_id    CHAR(36)     NOT NULL,
    name                VARCHAR(150) NOT NULL,
    trigger_type        VARCHAR(30)  NOT NULL,
    channel             VARCHAR(10)  NOT NULL,               -- telegram | sms
    message_template     TEXT         NOT NULL,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,
    created_by_user_id   CHAR(36)     NULL,
    created_at            TIMESTAMP    NULL,
    updated_at            TIMESTAMP    NULL,

    KEY idx_campaigns_company (owner_company_id),
    CONSTRAINT fk_campaigns_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_campaigns_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۷: campaign_logs — تاریخچه ارسال (فعلاً فقط شبیه‌سازی‌شده)
-- status: در این فاز همیشه 'simulated' — تا اتصال API واقعی
-- =====================================================================
CREATE TABLE campaign_logs (
    id                       CHAR(36)     NOT NULL PRIMARY KEY,
    campaign_id               CHAR(36)     NOT NULL,
    contact_site_profile_id   CHAR(36)     NOT NULL,
    channel                   VARCHAR(10)  NOT NULL,
    status                    VARCHAR(20)  NOT NULL DEFAULT 'simulated',
    payload                   JSON         NULL,               -- متن نهایی پیام
    sent_at                   TIMESTAMP    NULL,

    KEY idx_campaign_logs_campaign (campaign_id),
    KEY idx_campaign_logs_profile (contact_site_profile_id),
    CONSTRAINT fk_campaign_logs_campaign FOREIGN KEY (campaign_id)             REFERENCES campaigns(id),
    CONSTRAINT fk_campaign_logs_profile  FOREIGN KEY (contact_site_profile_id) REFERENCES contact_site_profiles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۸: tickets — تیکت پشتیبانی
-- status: open | in_progress | resolved | closed
-- priority: low | normal | high
-- =====================================================================
CREATE TABLE tickets (
    id                       CHAR(36)     NOT NULL PRIMARY KEY,
    contact_site_profile_id   CHAR(36)     NOT NULL,
    owner_company_id          CHAR(36)     NOT NULL,
    subject                   VARCHAR(200) NOT NULL,
    description                TEXT         NULL,
    status                     VARCHAR(20)  NOT NULL DEFAULT 'open',
    priority                   VARCHAR(10)  NOT NULL DEFAULT 'normal',
    assigned_to_user_id        CHAR(36)     NULL,
    created_by_user_id         CHAR(36)     NULL,
    created_at                 TIMESTAMP    NULL,
    updated_at                 TIMESTAMP    NULL,
    deleted_at                 TIMESTAMP    NULL,

    KEY idx_tickets_company_status (owner_company_id, status),
    KEY idx_tickets_profile (contact_site_profile_id),
    CONSTRAINT fk_tickets_profile      FOREIGN KEY (contact_site_profile_id) REFERENCES contact_site_profiles(id),
    CONSTRAINT fk_tickets_company      FOREIGN KEY (owner_company_id)        REFERENCES companies(id),
    CONSTRAINT fk_tickets_assigned_to  FOREIGN KEY (assigned_to_user_id)     REFERENCES users(id),
    CONSTRAINT fk_tickets_created_by   FOREIGN KEY (created_by_user_id)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۹: ticket_replies — پاسخ‌های تیکت (بدون ویرایش، فقط ثبت)
-- =====================================================================
CREATE TABLE ticket_replies (
    id           CHAR(36)  NOT NULL PRIMARY KEY,
    ticket_id    CHAR(36)  NOT NULL,
    user_id      CHAR(36)  NOT NULL,
    message      TEXT      NOT NULL,
    created_at    TIMESTAMP NULL,

    KEY idx_ticket_replies_ticket (ticket_id),
    CONSTRAINT fk_ticket_replies_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    CONSTRAINT fk_ticket_replies_user   FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- یادآوری قرارداد نام‌گذاری و معماری:
-- 1. contacts عمداً owner_company_id ندارد — بین‌شرکتی، مثل holidays.
--    هرگز BelongsToCompany روی مدل Contact نگذار.
-- 2. بقیه جدول‌ها owner_company_id طبق قرارداد استاندارد دارند.
-- 3. ستون‌های "TODO" (source_order_id در interactions، contract_id در
--    leads) عمداً بدون FK هستند چون جدول مقصد هنوز نیست — هر دو در
--    BACKLOG.md ثبت شده‌اند.
-- 4. campaign_logs.status همیشه 'simulated' در این فاز — طبق تصمیم
--    کارفرما (بدون کلید API واقعی).
-- 5. assigned_to_user_id (leads, tickets) — نقش کامل، نه user_id خام،
--    چون با created_by_user_id متفاوت است.
-- =====================================================================
