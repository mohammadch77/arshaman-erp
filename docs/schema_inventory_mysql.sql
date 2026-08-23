-- =====================================================================
-- ERP آرشامان — اسکیمای گروه ب: عملیاتی و انبار
-- MySQL 8.0+
-- نام‌گذاری ستون‌ها طبق docs/DATABASE_CONVENTIONS.md
-- تصمیم‌های تأییدشده: ارزش‌گذاری میانگین موزون، چندانباری با انبارهای نام‌دار بین‌شرکتی
-- این فایل مرجع ساختار است؛ در عمل migration های Laravel ساخته می‌شوند.
-- =====================================================================

-- =====================================================================
-- جدول ۰: product_categories — دسته‌بندی محصولات (پیش‌نیاز products.category_id)
-- ⚠️ این جدول از Session قبلی ماژول Catalog می‌آید (batch ۲۶ در migrations
-- واقعی، migration 2026_08_06_100001_create_product_categories_table)، نه
-- بخشی از طراحی اصلی این سند — اینجا فقط برای تکمیل مرجع اضافه شده تا
-- FK واقعی products.category_id قابل پیگیری باشد. بدون CRUD/UI در پروژه
-- (طبق تصمیم Session Catalog، فقط FK واقعی برای آینده).
-- =====================================================================
CREATE TABLE product_categories (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    owner_company_id    CHAR(36)      NOT NULL,
    name                VARCHAR(100)  NOT NULL,
    is_active           TINYINT(1)    NOT NULL DEFAULT 1,
    created_by_user_id  CHAR(36)      NULL,
    updated_by_user_id  CHAR(36)      NULL,
    created_at          TIMESTAMP     NULL,
    updated_at          TIMESTAMP     NULL,
    deleted_at          TIMESTAMP     NULL,

    KEY idx_product_categories_company (owner_company_id),
    CONSTRAINT fk_product_categories_company     FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_product_categories_created_by  FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_product_categories_updated_by  FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۱: products — محصولات و خدمات
-- fulfillment_type در سطح محصول است، نه شرکت (طبق بند ۵.۳ CLAUDE.md)
-- ترتیب ستون‌ها و کل ساختار زیر دقیقاً از SHOW CREATE TABLE روی
-- arshaman_erp واقعی گرفته شده (تأیید‌شده ۲۰۲۶-۰۸-۲۳) — نگاه کن یادداشت
-- پایان فایل درباره‌ی category_id/reorder_point/woocommerce_product_id.
-- =====================================================================
CREATE TABLE products (
    id                      CHAR(36)      NOT NULL PRIMARY KEY,
    owner_company_id        CHAR(36)      NOT NULL,
    category_id             CHAR(36)      NULL,               -- FK به product_categories؛ اختیاری
    name                    VARCHAR(150)  NOT NULL,
    sku                     VARCHAR(50)   NULL,               -- اختیاری: محصول دیجیتال/خدمت لزوماً کد انبار ندارد
    sale_price              DECIMAL(18,2) NOT NULL,
    cost_price              DECIMAL(18,2) NULL,               -- ممکن است هنوز نامشخص باشد
    reorder_point           INT           NULL,               -- خالی یعنی هشدار موجودی کم بی‌معناست
    currency_id             CHAR(36)      NULL,               -- FK واقعی؛ خالی = تومان
    fulfillment_type        ENUM('physical','digital','service') NOT NULL DEFAULT 'physical',
    unit_of_measure         ENUM('piece','kilogram','liter','meter','box') NOT NULL DEFAULT 'piece',
    woocommerce_product_id  VARCHAR(50)   NULL,
    is_active               TINYINT(1)    NOT NULL DEFAULT 1,
    created_by_user_id      CHAR(36)      NULL,
    updated_by_user_id      CHAR(36)      NULL,
    created_at              TIMESTAMP     NULL,
    updated_at              TIMESTAMP     NULL,
    deleted_at              TIMESTAMP     NULL,

    UNIQUE KEY uq_products_company_sku (owner_company_id, sku),   -- NULL ها با هم برخورد نمی‌کنند (چند محصول بدون sku مجازند)
    KEY idx_products_company (owner_company_id),
    KEY idx_products_fulfillment (owner_company_id, fulfillment_type),
    KEY idx_products_category (category_id),
    CONSTRAINT fk_products_category    FOREIGN KEY (category_id)        REFERENCES product_categories(id),
    CONSTRAINT fk_products_company     FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_products_currency    FOREIGN KEY (currency_id)        REFERENCES currencies(id),
    CONSTRAINT fk_products_created_by  FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_products_updated_by  FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
    -- توجه: CHECK دیگر لازم نیست — ENUM خودش محدودیت مقدار را تضمین می‌کند
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۲: warehouses — انبارهای فیزیکی نام‌دار
-- ⚠️ عمداً owner_company_id ندارد — بین‌شرکتی، مثل holidays/contacts.
-- هرگز BelongsToCompany روی مدل این جدول نگذار.
-- =====================================================================
CREATE TABLE warehouses (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    name                VARCHAR(150)  NOT NULL,
    address             TEXT          NULL,
    is_active           TINYINT(1)    NOT NULL DEFAULT 1,
    created_by_user_id  CHAR(36)      NULL,
    created_at          TIMESTAMP     NULL,
    updated_at          TIMESTAMP     NULL,

    CONSTRAINT fk_warehouses_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۳: stocks — موجودی هر محصول در هر انبار، به تفکیک شرکت مالک
-- quantity_on_hand کش‌شده و فقط از طریق stock_movements تغییر می‌کند.
-- average_cost برای ارزش‌گذاری میانگین موزون (تصمیم تأییدشده).
-- =====================================================================
CREATE TABLE stocks (
    id                   CHAR(36)      NOT NULL PRIMARY KEY,
    warehouse_id         CHAR(36)      NOT NULL,
    product_id           CHAR(36)      NOT NULL,
    owner_company_id     CHAR(36)      NOT NULL,             -- شرکت مالک این موجودی
    quantity_on_hand     DECIMAL(18,4) NOT NULL DEFAULT 0,    -- ۴ رقم اعشار برای واحدهای غیرصحیح
    reorder_point        DECIMAL(18,4) NULL,
    average_cost         DECIMAL(18,2) NULL,                  -- میانگین موزون بهای هر واحد
    created_at           TIMESTAMP     NULL,
    updated_at           TIMESTAMP     NULL,

    UNIQUE KEY uq_stocks_warehouse_product_company (warehouse_id, product_id, owner_company_id),
    KEY idx_stocks_company_product (owner_company_id, product_id),
    CONSTRAINT fk_stocks_warehouse FOREIGN KEY (warehouse_id)      REFERENCES warehouses(id),
    CONSTRAINT fk_stocks_product   FOREIGN KEY (product_id)        REFERENCES products(id),
    CONSTRAINT fk_stocks_company   FOREIGN KEY (owner_company_id)  REFERENCES companies(id),
    CONSTRAINT chk_stocks_non_negative CHECK (quantity_on_hand >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۴: stock_movements — دفترچه حرکت موجودی (منبع حقیقت)
-- quantity همیشه مثبت است؛ جهت از movement_type مشخص می‌شود.
-- unit_cost فقط برای حرکت‌های ورودی (محاسبه میانگین موزون) پر می‌شود.
-- =====================================================================
CREATE TABLE stock_movements (
    id                  CHAR(36)      NOT NULL PRIMARY KEY,
    stock_id            CHAR(36)      NOT NULL,
    movement_type       ENUM('purchase_in','sale_out','return_in','adjustment_in','adjustment_out','waste_out') NOT NULL,
    quantity            DECIMAL(18,4) NOT NULL,
    unit_cost           DECIMAL(18,2) NULL,
    reference_note      TEXT          NULL,
    created_by_user_id  CHAR(36)      NULL,
    occurred_at         TIMESTAMP     NOT NULL,
    created_at          TIMESTAMP     NULL,

    KEY idx_stock_movements_stock_date (stock_id, occurred_at),
    KEY idx_stock_movements_type (movement_type),
    CONSTRAINT fk_stock_movements_stock      FOREIGN KEY (stock_id)           REFERENCES stocks(id),
    CONSTRAINT fk_stock_movements_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT chk_stock_movements_qty_positive CHECK (quantity > 0)
    -- توجه: CHECK نوع حرکت لازم نیست — ENUM خودش تضمین می‌کند؛ CHECK مقدار مثبت همچنان لازم است (قاعده‌ای غیر از enum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۵: orders — سفارش‌ها
-- order_status پوشش هر دو چرخه (فیزیکی/دیجیتال) — طبق بند ۴.۳ سند طراحی.
-- exchange_rate_snapshot طبق بند ۵.۲ CLAUDE.md (snapshot، نه reference زنده).
-- UNIQUE(owner_company_id, source, external_order_id) طبق بند ۵.۴ (idempotency).
-- =====================================================================
CREATE TABLE orders (
    id                       CHAR(36)      NOT NULL PRIMARY KEY,
    owner_company_id         CHAR(36)      NOT NULL,
    order_number             BIGINT UNSIGNED NOT NULL,        -- ترتیبی، تولید خودکار بک‌اند (نه ورودی کاربر)
    party_id                 CHAR(36)      NOT NULL,          -- مشتری
    order_status             ENUM('received','paid','preparing','shipped','delivered',
                                    'delivered_instant','closed','cancelled','returned')
                                   NOT NULL DEFAULT 'received',
    source                   ENUM('woocommerce','manual_instagram','manual_telegram','manual_other') NOT NULL,
    external_order_id        VARCHAR(100)  NULL,
    exchange_rate_snapshot   DECIMAL(18,2) NULL,
    currency_id              CHAR(36)      NULL,
    subtotal_amount          DECIMAL(18,2) NOT NULL DEFAULT 0,
    shipping_amount          DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_amount             DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by_user_id       CHAR(36)      NULL,
    updated_by_user_id       CHAR(36)      NULL,
    created_at               TIMESTAMP     NULL,
    updated_at               TIMESTAMP     NULL,
    deleted_at                TIMESTAMP     NULL,

    UNIQUE KEY uq_orders_company_number (owner_company_id, order_number),    -- شماره سفارش نمایشی، یکتا به‌ازای هر شرکت
    UNIQUE KEY uq_orders_company_source_external (owner_company_id, source, external_order_id),
    KEY idx_orders_company_status (owner_company_id, order_status),
    KEY idx_orders_party (party_id),
    CONSTRAINT fk_orders_company     FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_orders_party       FOREIGN KEY (party_id)           REFERENCES parties(id),
    CONSTRAINT fk_orders_currency    FOREIGN KEY (currency_id)        REFERENCES currencies(id),
    CONSTRAINT fk_orders_created_by  FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_orders_updated_by  FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
    -- توجه: CHECK این دو ستون لازم نیست — ENUM خودش تضمین می‌کند
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۶: order_lines — اقلام سفارش (Snapshot سه‌گانه — قابل مذاکره نیست)
-- unit_sale_price_amount, unit_cost_amount, fulfillment_type همه
-- کپی لحظه فروش‌اند، نه reference زنده به products.
-- =====================================================================
CREATE TABLE order_lines (
    id                      CHAR(36)      NOT NULL PRIMARY KEY,
    order_id                CHAR(36)      NOT NULL,
    product_id              CHAR(36)      NOT NULL,
    quantity                DECIMAL(18,4) NOT NULL,
    unit_sale_price_amount  DECIMAL(18,2) NOT NULL,           -- snapshot
    unit_cost_amount        DECIMAL(18,2) NULL,               -- snapshot، ممکن است نامشخص باشد
    fulfillment_type        ENUM('physical','digital','service') NOT NULL,  -- snapshot از products.fulfillment_type
    line_total_amount       DECIMAL(18,2) NOT NULL,

    KEY idx_order_lines_order (order_id),
    KEY idx_order_lines_product (product_id),
    CONSTRAINT fk_order_lines_order   FOREIGN KEY (order_id)   REFERENCES orders(id),
    CONSTRAINT fk_order_lines_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT chk_order_lines_qty_positive CHECK (quantity > 0)
    -- توجه: CHECK نوع تحویل لازم نیست — ENUM خودش تضمین می‌کند
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- جدول ۷: shipments — ارسال و پیگیری مرسوله
-- carrier پیش‌فرض 'tipax' طبق تصمیم کارفرما (DECISIONS.md)، ساختار باز برای بقیه.
-- shipping_cost_amount بخشی از بهای تمام‌شده سفارش (بند ۴.۲ سند).
-- =====================================================================
CREATE TABLE shipments (
    id                       CHAR(36)      NOT NULL PRIMARY KEY,
    order_id                 CHAR(36)      NOT NULL,
    owner_company_id         CHAR(36)      NOT NULL,
    carrier                  VARCHAR(30)   NOT NULL DEFAULT 'tipax',   -- عمداً VARCHAR، نه ENUM — شرکت حمل، مقدار باز (بدون نیاز به migration برای مقدار جدید)
    tracking_code            VARCHAR(100)  NULL,
    status                   ENUM('pending','packed','shipped','delivered') NOT NULL DEFAULT 'pending',
    shipped_at               TIMESTAMP     NULL,
    delivered_at             TIMESTAMP     NULL,
    shipping_cost_amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_by_user_id       CHAR(36)      NULL,
    created_at               TIMESTAMP     NULL,
    updated_at               TIMESTAMP     NULL,

    KEY idx_shipments_order (order_id),
    KEY idx_shipments_company_status (owner_company_id, status),
    CONSTRAINT fk_shipments_order      FOREIGN KEY (order_id)           REFERENCES orders(id),
    CONSTRAINT fk_shipments_company    FOREIGN KEY (owner_company_id)   REFERENCES companies(id),
    CONSTRAINT fk_shipments_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    -- توجه: CHECK لازم نیست — ENUM خودش تضمین می‌کند
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- یادآوری قرارداد نام‌گذاری و معماری:
-- 1. warehouses عمداً owner_company_id ندارد — بین‌شرکتی، مثل
--    holidays (HR) و contacts (CRM). هرگز BelongsToCompany رویش نگذار.
-- 2. stocks.owner_company_id = شرکت مالک موجودی، نه انبار.
-- 3. quantity_on_hand هرگز مستقیم UPDATE نمی‌شود — فقط از طریق Action
--    های ReceiveStock/IssueStock/AdjustStock که هرکدام یک ردیف
--    stock_movements می‌سازند و quantity_on_hand را در همان تراکنش
--    (با lockForUpdate) به‌روز می‌کنند.
-- 4. دفاع دولایه موجودی منفی: چک سطح Action + CHECK دیتابیس
--    (chk_stocks_non_negative) — دقیقاً الگوی parties/national_id.
-- 5. order_lines: سه ستون snapshot (unit_sale_price_amount,
--    unit_cost_amount, fulfillment_type) هرگز به products وصل
--    نمی‌مانند؛ کپی لحظه فروش‌اند.
-- 6. UNIQUE(owner_company_id, source, external_order_id) در orders:
--    وقتی external_order_id NULL است (سفارش دستی)، MySQL هر تعداد
--    NULL را در UNIQUE مجاز می‌داند (NULL != NULL) — یعنی این قید
--    فقط برای سفارش‌های ووکامرسی idempotency واقعی ایجاد می‌کند.
-- 7. orders.order_number: شماره ترتیبی نمایشی («سفارش شماره ۱۰۵»)،
--    هرگز توسط کاربر وارد نمی‌شود — Action با lockForUpdate() روی
--    آخرین سفارش همان شرکت، شماره بعدی را محاسبه می‌کند (چون
--    AUTO_INCREMENT واقعی MySQL نمی‌تواند per-company صفر شود).
-- 8. products.sku اختیاری است — محصول دیجیتال/خدمت (Pixentry، خدمات
--    آرشامان) لزوماً کد انبارداری فیزیکی ندارد؛ اجبار به sku یعنی
--    کاربر مجبور می‌شود کد الکی بسازد فقط برای رد اعتبارسنجی.
-- 9. ⚠️ تغییر قرارداد کل پروژه (تصمیم کارفرما، مستند در DECISIONS.md):
--    از این ماژول به بعد، enum های کسب‌وکاری بسته و کنترل‌شده توسط
--    توسعه‌دهنده (fulfillment_type, movement_type, order_status,
--    source, status) از VARCHAR+CHECK به ENUM نیتیو MySQL تغییر کردند.
--    قانون اجباری: مقادیر جدید همیشه به انتهای لیست ENUM اضافه شوند،
--    هرگز ترتیب موجود عوض یا مقداری حذف نشود (ریسک نگاشت داخلی MySQL).
--    ماژول‌های قبلی پروژه (Auth, HR, Core, CRM) که با VARCHAR+CHECK
--    ساخته شدند، فعلاً دست‌نخورده می‌مانند — تبدیل عقب‌رونده آن‌ها یک
--    تصمیم/Session جداست، نه بخشی از این کار.
-- 10. shipments.carrier عمداً ENUM نشد — بر خلاف بقیه enum های این
--     فایل، این یک مقدار باز است که ادمین باید بتواند بدون تغییر کد
--     مقدار جدید اضافه کند (طبق تصمیم کارفرما در DECISIONS.md).
-- 11. Action های حساس این ماژول (ReceiveStock, IssueStock, AdjustStock,
--     TransitionOrderStatus) باید مثل ReopenPayrollRun در HR صریح
--     activity()->log() بزنند — این یک شکاف بود که در بررسی مشترک با
--     کارفرما کشف و در نقشه Session ها اصلاح شد.
-- 12. products.unit_of_measure روی محصول است، نه stocks — چون واحد
--     یک محصول همیشه ثابت است (یک تی‌شرت همیشه «عدد» است)، در حالی
--     که همان محصول می‌تواند در چند انبار/شرکت موجودی داشته باشد؛
--     نگه‌داری واحد در stocks یعنی تکرار داده و ریسک ناهماهنگی
--     (یک ردیف «عدد»، ردیف دیگر همان محصول «کیلوگرم» — یک باگ داده).
--     طبق تصمیم کارفرما: بدون سطح قفسه/ردیف و بدون تاریخ انقضا —
--     محصولات فعلی این نیازها را ندارند.
-- 13. ⚠️ products.category_id، products.reorder_point و
--     products.woocommerce_product_id از این سند طراحی نشده بودند —
--     از یک Session قبلی‌تر ماژول Catalog می‌آیند (migration های واقعی
--     2026_08_06_100002_create_products_table و
--     2026_08_07_100004_add_reorder_point_to_products_table، batch ۲۶/۲۷
--     در جدول migrations واقعی، هر دو زودتر از نگارش این سند). جدول ۰
--     (product_categories) هم به همین دلیل اضافه شد — پیش‌نیاز واقعی
--     products.category_id بود که تا امروز در هیچ سند طراحی این پروژه
--     نیامده بود. کشف شد حین بررسی مشترک با کارفرما قبل از commit
--     Session sku/unit_of_measure (۲۰۲۶-۰۸-۲۳)؛ ساختار کامل هر دو جدول
--     در بالا مستقیماً از SHOW CREATE TABLE روی arshaman_erp واقعی گرفته
--     شده، نه از حافظه یا فرض. product_categories بدون CRUD/UI است —
--     همان Session قبلی عمداً فقط FK واقعی برای آینده ساخته بود.
-- =====================================================================
