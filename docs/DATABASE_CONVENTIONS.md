# DATABASE_CONVENTIONS.md — قرارداد نام‌گذاری دیتابیس
## پروژه ERP آرشامان

> هدف این سند: هیچ‌کس (نه Claude Code، نه تو، نه یک برنامه‌نویس جدید که بعداً می‌آید) نباید برای فهمیدن
> «این ستون دقیقاً چه چیزی را نگه می‌دارد» کد را باز کند. اسم ستون خودش باید جواب بدهد.
> این سند مکمل بخش ۳ فایل `CLAUDE.md` است؛ آنجا فقط خلاصه و ارجاع به این‌جا آمده.

---

## اصل کلی

هر ستون باید به این سؤال جواب بدهد: **«اگر فقط اسم این ستون را ببینم، بدون دیدن کد یا مدل، می‌فهمم چه رابطه‌ای با چه چیزی دارد؟»**
اگر جواب «نه، باید بروم کد را نگاه کنم» است، اسم ستون ضعیف است.

---

## ۱. ستون‌های ردگیری (Audit) — همیشه با نقش کامل

```
created_by   →  created_by_user_id     (FK → users.id)
updated_by   →  updated_by_user_id     (FK → users.id)
             →  deleted_by_user_id     (جدید — کِی soft delete شد، کی حذف کرد)
```

این سه ستون روی **همه جدول‌های عملیاتی** می‌آیند (نه جدول‌های سراسری خیلی ساده مثل `roles`/`permissions` که تغییرشان نادر و توسط seeder است).

**قانون تکمیلی:** هر سه باید constraint واقعی `FOREIGN KEY ... REFERENCES users(id)` داشته باشند، نه فقط CHAR(36) بدون قید — این هم درستی داده را تضمین می‌کند هم (چون FK در MySQL خودکار ایندکس می‌سازد) به راندمان کوئری کمک می‌کند.

---

## ۲. کلید خارجی — قانون: همیشه نقش کامل، حتی اگر روشن باشد

طبق تصمیم پروژه، **هر FK نام نقشش را با خودش حمل می‌کند**، حتی وقتی جدول فقط یک ارتباط با آن جدول مقصد دارد. یعنی به‌جای تکیه به قرارداد ضمنی، همیشه صریح می‌نویسیم:

```
company_id      →  owner_company_id      (شرکتی که این رکورد را مالک است — از طریق BelongsToCompany)
user_id         →  به‌جای user_id خام، بگو نقشش چیست:
                    requested_by_user_id / approved_by_user_id / assigned_to_user_id / invited_by_user_id
role_id         →  assigned_role_id       (وقتی در جدول واسط user_company_roles نقش را نگه می‌دارد)
```

برای جدول‌هایی که واقعاً فقط یک رابطه ساده و طبیعی با جدول مقصد دارند و نقش دیگری غیر از «همان موجودیت» ندارند
(مثل `product_id` در `order_lines`، یا `currency_id` در `exchange_rates`)، نام نقش با نام موجودیت یکی می‌شود —
این خودش «نقش کامل» است، چیزی برای اضافه‌کردن نیست.

### owner_company_id — تغییر پایه‌ای

از این به بعد، trait اصلی چندشرکتی‌بودن به این شکل است (نسخه قبلی `CLAUDE.md` را جایگزین می‌کند):

```php
// app/Modules/Core/Concerns/BelongsToCompany.php
trait BelongsToCompany {
    protected static function bootBelongsToCompany(): void {
        static::addGlobalScope('owner_company', fn($q) =>
            $q->where('owner_company_id', app(CompanyContext::class)->id())
        );
        static::creating(fn($m) =>
            $m->owner_company_id ??= app(CompanyContext::class)->id()
        );
    }
}
```

هر جدول عملیاتی که قبلاً `company_id` داشت (یا در مستندات فازهای بعدی طراحی شده)، از این به بعد `owner_company_id` می‌نویسیم.

---

## ۳. وضعیت‌ها (Enum/Status) — هرگز فقط `status`

اگر جدولی ممکن است بیش از یک بعد وضعیت داشته باشد (حالا یا در آینده)، اسم بعد را در ستون بیاور:

```
status          →  order_status         (چون بعداً payment_status هم جدا لازم می‌شود)
                →  shipment_status
                →  invoice_status
```

اگر یک جدول قطعاً فقط یک بعد وضعیت خواهد داشت و هیچ‌وقت بعد دوم پیدا نمی‌کند (نادر است)، همان `status` قابل قبول است — ولی پیش‌فرض همیشه نام‌دار کردن است.

---

## ۴. مبالغ و ارز

```
amount          →  sale_amount / cost_amount / shipping_amount / gross_amount / net_amount
```
هر ستون مبلغی یا کنار خودش `currency_id` دارد، یا صراحتاً یک snapshot با نام گویاست:
```
exchange_rate           →  exchange_rate_at_order    (وقتی چند نرخ در جدول‌های مختلف معنا دارند و باید مشخص شود مال چه لحظه‌ای است)
```
در جایی که فقط یک نرخ در کل جدول معنا دارد (مثل `orders.exchange_rate`، طبق تعریف بخش ۵.۲ CLAUDE.md که خودش snapshot سفارش است)، همان `exchange_rate` کافی است چون نقش کامل است.

---

## ۵. بولین‌ها

پیشوند `is_`/`has_` (از قبل رعایت شده): `is_active`, `is_super_admin`, `has_paid`.

---

## ۶. تاریخ و زمان

- `_at` برای لحظه دقیق با ساعت: `paid_at`, `shipped_at`, `delivered_at`, `accepted_at`
- `_date` برای تاریخ تقویمی بدون ساعت: `effective_date`, `expires_date`

---

## ۷. ستون‌های Snapshot

هر ستونی که مقدارش هنگام ثبت **کپی** شده (نه reference زنده)، اگر نامش به‌تنهایی این را نمی‌رساند، باید گویا شود.
مثال‌های فعلی پروژه (`orders.exchange_rate`, `order_lines.cost_amount`, `order_lines.fulfillment_type`) چون در بخش ۵.۲ CLAUDE.md صراحتاً به‌عنوان snapshot مستند شده‌اند، نیازی به تغییر اسم ندارند — مستندسازی جای تغییر اسم را همان‌جا گرفته.

---

## ۸. جدول‌های واسط (Pivot/Junction)

نام‌گذاری: `جدول_اول_جدول_دوم` به ترتیب منطقی (نه الفبایی صرف)، طبق الگوی موجود:
```
role_permission        (نقش ↔ مجوز)
user_company_roles     (کاربر ↔ شرکت ↔ نقش — قلب سیستم دسترسی)
```
ستون‌های داخل این جدول‌ها هم از قانون بخش ۲ پیروی می‌کنند (`user_id`, `owner_company_id` یا `assigned_role_id` طبق نقش).

---

## ۹. ایندکس و Constraint — نام‌گذاری (از `schema_auth_mysql.sql` رسمی شد)

```
fk_<جدول>_<نقش‌ستون>      مثال: fk_ucr_user, fk_inv_company
uq_<جدول>_<ستون‌ها>        مثال: uq_user_company (روی user_id+owner_company_id)
idx_<جدول>_<ستون‌ها>       مثال: idx_activity_company
```

**قانون راندمان:** هر FK باید ایندکس داشته باشد. در MySQL هر `FOREIGN KEY` خودکار ایندکس می‌سازد — پس هرگز یک رابطه FK را بدون قید `CONSTRAINT ... FOREIGN KEY` تعریف نکن، حتی اگر فقط برای ردگیری (audit) باشد.

---

## ۱۰. استثنا: ستون‌های پکیج‌های خارجی

جدول‌هایی که توسط پکیج‌های خارجی ساخته می‌شوند (`sessions` توسط Laravel، `activity_log` توسط Spatie) ساختار داخلی‌شان دست ما نیست — نام ستون‌هایشان (`user_id`, `causer_id`, `subject_id`, `subject_type`) را همان‌طور که پکیج تعریف کرده نگه می‌داریم. این قرارداد فقط روی جدول‌هایی که خودمان طراحی می‌کنیم اعمال می‌شود.

---

## ۱۱. جدول مرجع سریع (قبل → بعد)

| قبل | بعد |
|---|---|
| `company_id` | `owner_company_id` |
| `created_by` | `created_by_user_id` |
| `updated_by` | `updated_by_user_id` |
| `invited_by` | `invited_by_user_id` |
| `status` (بدون بعد مشخص) | `<چیزی>_status` |
| `amount` (بدون واحد/نقش) | `<نقش>_amount` |

---

## ۱۲. چه چیزی در `schema_auth_mysql.sql` عوض شد

- `companies.created_by/updated_by` → `created_by_user_id/updated_by_user_id` + قید FK واقعی به `users(id)` اضافه شد
- `users.created_by/updated_by` → همان تغییر
- `user_company_roles.created_by` → `created_by_user_id` + قید FK
- `user_invitations.invited_by` → `invited_by_user_id` (قید FK از قبل وجود داشت، فقط نام عوض شد)
- هیچ‌کدام از جدول‌های این ماژول هنوز `company_id` به‌معنای BelongsToCompany ندارند (چون `companies`/`users`/`roles` سراسری‌اند) — قانون `owner_company_id` از اولین ماژول عملیاتی بعدی (مثلاً Parties یا Products) اعمال می‌شود.
