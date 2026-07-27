# پروژه کوچک ۱: احراز هویت و مدیریت کاربران
## ماژول Core/Auth — پایه کل سیستم ERP آرشامان

> این اولین تکه است. با Claude Code، session به session جلو برو.
> هر session یک بخش، بعد تست و commit و clear.
>
> **نام‌گذاری ستون‌ها طبق `docs/DATABASE_CONVENTIONS.md` است:** `owner_company_id` نه `company_id`،
> `created_by_user_id`/`updated_by_user_id` نه خام، `assigned_role_id` در `user_company_roles`.
> `schema_auth_mysql.sql` از قبل با این نام‌ها به‌روزرسانی شده — از همان کپی کن، بازنویسی نکن.

---

## تصمیم معماری مهم (قبل از شروع بخوان)

در یک ERP داخلی، کاربرها **اعضای تیم** هستند نه عموم مردم. بنابراین:

- **ثبت‌نام عمومی وجود ندارد.** هیچ‌کس نمی‌تواند خودش را ثبت کند.
- **فقط ادمین کل کاربر می‌سازد** و به او نقش و شرکت می‌دهد.
- کاربر جدید یا مستقیم توسط ادمین ساخته می‌شود، یا با **دعوت‌نامه** (لینک با توکن) رمزش را تعیین می‌کند.

این هم امن‌تر است و هم با مدل نقش×شرکت سازگار.

پس در این پروژه می‌سازیم:
1. صفحه **ورود** (Login) — برای همه
2. **پیشخوان** (Dashboard) با پوسته راست‌چین و سوییچر شرکت
3. **مدیریت کاربران** (فقط ادمین): فهرست، ساخت، ویرایش، تخصیص نقش×شرکت
4. **دعوت‌نامه** (اختیاری، بخش آخر)

---

## این پروژه چطور «قابل توسعه» می‌ماند

هر بخش یک واحد مستقل است. بعداً برای افزودن قابلیت (مثلاً احراز دو مرحله‌ای، یا نقش‌های سفارشی)، فقط یک بخش جدید اضافه می‌کنی بدون دست‌زدن به بقیه. کلید این کار:

- **منطق در Action ها** (نه در کامپوننت Livewire): هر عمل یک کلاس مستقل
- **دسترسی در Policy ها**: قواعد دسترسی جدا از منطق
- **ماژول جدا**: مدل/Action/Policy در `app/Modules/Core` می‌ماند؛ کامپوننت‌های Livewire در `app/Livewire/Core`

---

## مدل داده (۹ جدول)

فایل `schema_auth_mysql.sql` را ببین. خلاصه:

| جدول | نقش |
|---|---|
| `companies` | شرکت‌ها + نوع کسب‌وکار |
| `users` | کاربران داخلی + فلگ ادمین کل |
| `roles` | نقش‌ها |
| `permissions` | مجوزهای ریزدانه |
| `role_permission` | واسط نقش↔مجوز |
| `user_company_roles` | **قلب سیستم:** کاربر × شرکت × نقش |
| `user_invitations` | دعوت‌نامه (جایگزین ثبت‌نام) |
| `sessions` | نشست + شرکت فعال |
| `activity_log` | رد ممیزی |

---

# تکه‌بندی به Session ها

## Session 0 — راه‌اندازی تم (پیش‌نیاز Session 1)

قبل از هر ماژول کسب‌وکاری، تم قابل‌جایگزین را راه‌اندازی کن. پرامپت کامل در `docs/THEME_STRUCTURE.md` بخش ۵ آمده — Livewire 3 + Mary UI نصب و راه‌اندازی شود، ساختار `config/theme.php`، `app-logo.blade.php` و `app/Support/Farsi.php` ساخته شود، و یک صفحه تست راست‌چین با رنگ/آیکون از تم مرکزی نشان داده شود.

**تعریف تمام:** صفحه تست راست‌چین است، رنگ و آیکون از منبع مرکزی می‌آید.

---

## Session 1 — پایه: شرکت و کاربر و ورود

**پرامپت آماده:**
```
فایل CLAUDE.md را بخوان. سپس برای این بخش اول نقشه بده، هنوز کد نزن:

ماژول Core را بساز با:
1. Migration های companies و users طبق schema_auth_mysql.sql
   (business_type را VARCHAR کنترل‌شده با enum PHP بگذار، UUID برای id، soft delete)
2. Model های Company و User با:
   - trait BelongsToCompany روی مدل‌های آینده (فعلاً فقط تعریف trait)
   - User: پیاده‌سازی Authenticatable لاراول
3. Seeder: شش شرکت (arshaman, verifex, tkart, doano, pixentry, shared)
4. Command artisan برای ساخت ادمین کل:
   php artisan erp:create-admin
   که email و password و full_name بگیرد و is_super_admin=true بسازد
5. کامپوننت Livewire صفحه Login + Blade view، راست‌چین، فونت Vazirmatn، با کامپوننت‌های Mary UI
6. تست: کاربر با رمز درست وارد شود، با رمز غلط پیام عمومی بگیرد

نساز: نقش‌ها، مدیریت کاربران، سوییچر شرکت — session بعد.
تمام وقتی: بتوانم ادمین بسازم و با آن وارد شوم.
```

**تعریف تمام:**
- [ ] `php artisan migrate:fresh --seed` شش شرکت می‌سازد
- [ ] `php artisan erp:create-admin` ادمین کل می‌سازد
- [ ] با ادمین می‌توانم وارد شوم
- [ ] رمز غلط پیام عمومی «اطلاعات نادرست» می‌دهد
- [ ] تست login سبز

بعد: تست، commit `feat(auth): company+user+login`، آپدیت CLAUDE.md، `/clear`

---

## Session 2 — نقش‌ها و دسترسی نقش×شرکت

**پرامپت آماده:**
```
CLAUDE.md را بخوان. برای این بخش نقشه بده، بعد پیاده کن:

روی ماژول Core:
1. Migration های roles, permissions, role_permission, user_company_roles
   طبق schema_auth_mysql.sql
2. Seeder نقش‌ها: holding_admin, accountant, operator, viewer
   و نمونه مجوزها
3. کلاس CompanyContext (singleton) که شرکت فعال session را نگه دارد:
   - id() شرکت فعال را برگرداند
   - set($companyId) با بررسی اینکه کاربر در آن شرکت نقش دارد
4. Gate/Policy: کاربر فقط به شرکتی دسترسی دارد که در user_company_roles
   نقش دارد. ادمین کل (is_super_admin) به همه دسترسی دارد.
5. Middleware به نام EnsureCompanyAccess

تست الزامی (طبق CLAUDE.md بخش ۷):
- کاربر با نقش در شرکت الف، درخواستش به داده شرکت ب → 403
- ادمین کل به همه شرکت‌ها دسترسی دارد

نساز: UI مدیریت کاربران — session بعد.
```

**تعریف تمام:**
- [ ] تست دسترسی متقاطع سبز (مهم‌ترین تست)
- [ ] ادمین کل همه را می‌بیند
- [ ] CompanyContext کار می‌کند

---

## Session 3 — سوییچر شرکت + پوسته پیشخوان

**پرامپت آماده:**
```
CLAUDE.md را بخوان. نقشه بده، بعد پیاده کن:

1. Layout اصلی پیشخوان با Blade + Mary UI:
   - dir="rtl"، فونت Vazirmatn
   - منوی کناری + هدر
2. کامپوننت Livewire سوییچر شرکت در هدر:
   - فقط شرکت‌هایی که کاربر در آن‌ها نقش دارد
   - ادمین کل: همه شرکت‌ها + گزینه «نمای تجمیعی هلدینگ»
   - تغییر شرکت → CompanyContext عوض شود و در session بماند (active_company_id)
3. منوی کناری بر پایه business_type شرکت فعال آیتم نشان دهد:
   - انبار فقط برای physical_goods و hybrid
   - پروژه فقط برای project_services
   (فعلاً آیتم‌ها لینک خالی، فقط ساختار)
4. کامپوننت Livewire صفحه پیشخوان ساده: نام کاربر، شرکت فعال، نقشش

تست: بعد از تغییر شرکت، شرکت فعال در رفرش حفظ شود.
```

**تعریف تمام:**
- [ ] سوییچر فقط شرکت‌های مجاز را نشان می‌دهد
- [ ] منو بر پایه نوع کسب‌وکار فیلتر می‌شود
- [ ] رفرش صفحه شرکت فعال را حفظ می‌کند

---

## Session 4 — مدیریت کاربران (فقط ادمین)

**پرامپت آماده:**
```
CLAUDE.md را بخوان. نقشه بده، بعد پیاده کن:

مدیریت کاربران، فقط برای ادمین کل و holding_admin:
1. Action ها (منطق در Action نه در کامپوننت Livewire):
   - CreateUser: ساخت کاربر با نام، ایمیل، رمز اولیه
   - AssignRole: تخصیص نقش×شرکت به کاربر (رکورد user_company_roles)
   - ToggleUserActive: فعال/غیرفعال
2. Policy: فقط ادمین کل و holding_admin به این بخش دسترسی دارند
3. کامپوننت‌های Livewire:
   - فهرست کاربران با وضعیت و نقش‌هایشان (جدول Mary UI با صفحه‌بندی)
   - فرم ساخت کاربر
   - صفحه تخصیص نقش×شرکت (کاربر می‌تواند در چند شرکت نقش داشته باشد)
4. activity_log برای ثبت ساخت/تغییر کاربر

تست:
- کاربر عادی نتواند به مدیریت کاربران دسترسی داشته باشد (403)
- ادمین بتواند کاربر بسازد و نقش×شرکت بدهد
```

**تعریف تمام:**
- [ ] فقط ادمین به مدیریت کاربران دسترسی دارد
- [ ] ساخت کاربر + تخصیص نقش×شرکت کار می‌کند
- [ ] تغییرات در activity_log ثبت می‌شوند

---

## Session 5 (اختیاری) — دعوت‌نامه

**پرامپت آماده:**
```
CLAUDE.md را بخوان. نقشه بده، بعد پیاده کن:

سیستم دعوت‌نامه (جایگزین امن‌تر ساخت مستقیم):
1. Action InviteUser: رکورد user_invitations با توکن یکتا و انقضا
2. ارسال ایمیل دعوت با لینک توکن‌دار (فعلاً log بجای ایمیل واقعی)
3. کامپوننت Livewire عمومی «قبول دعوت»: کاربر با توکن معتبر، نام و رمزش را تعیین می‌کند
4. پس از قبول: کاربر ساخته و نقش×شرکت دعوت به او داده شود
5. توکن منقضی یا استفاده‌شده رد شود

تست: توکن نامعتبر یا منقضی رد شود.
```

---

# ساختار نهایی ماژول (بعد از همه Session ها)

```
app/Modules/Core/
├── Models/
│   ├── Company.php
│   ├── User.php
│   ├── Role.php
│   ├── Permission.php
│   └── UserInvitation.php
├── Actions/
│   ├── CreateUser.php
│   ├── AssignRole.php
│   ├── ToggleUserActive.php
│   └── InviteUser.php
├── Policies/
│   └── UserPolicy.php
├── Services/
│   └── CompanyContext.php
├── Concerns/
│   └── BelongsToCompany.php
├── Console/
│   └── CreateAdminCommand.php
└── Database/
    ├── Migrations/
    └── Seeders/

app/Http/Middleware/
└── EnsureCompanyAccess.php

app/Livewire/Core/
├── Auth/Login.php
├── Dashboard.php
├── CompanySwitcher.php
├── Users/UserIndex.php
├── Users/UserCreate.php
└── Users/AssignRole.php

resources/views/livewire/core/
├── auth/login.blade.php
├── dashboard.blade.php
├── company-switcher.blade.php
├── users/user-index.blade.php
├── users/user-create.blade.php
└── users/assign-role.blade.php

resources/views/components/layouts/
└── app.blade.php        (پوسته راست‌چین + سوییچر)
```

---

# نکات حیاتی برای این ماژول

## ۱. trait BelongsToCompany
این ماژول خودش company-scoped نیست (کاربرها و شرکت‌ها سراسری‌اند)، اما **trait را همین‌جا تعریف کن** چون ماژول‌های بعدی (سفارش، هزینه...) به آن نیاز دارند. کد کامل در CLAUDE.md بخش ۵.۱.

## ۲. ادمین کل استثناست
`is_super_admin = true` یعنی کاربر به همه شرکت‌ها دسترسی دارد **بدون نیاز به رکورد در user_company_roles**. این را در Gate و CompanyContext لحاظ کن.

## ۳. رمز عبور
- هرگز plain ذخیره نشود (Hash::make)
- پیام خطای ورود همیشه عمومی: «ایمیل یا رمز نادرست است» (نه «رمز غلط»)
- حداقل ۸ کاراکتر

## ۴. تست اول
برای بخش امنیتی (دسترسی متقاطع)، تست را **قبل** از پیاده‌سازی بنویس. این تنها بخشی است که TDD واقعاً می‌ارزد.

---

# اولین قدم همین الان

۱. اگر هنوز پروژه Laravel نساخته‌ای، دستورات IMPLEMENTATION_PLAYBOOK بخش ۰.۲ را اجرا کن.
۲. `CLAUDE.md`، `schema_auth_mysql.sql` و همین فایل را در ریشه/docs بگذار.
۳. Claude Code را باز کن و پرامپت Session 0 (راه‌اندازی تم) را بزن.
۴. بعد پرامپت Session 1 را بزن.
۵. نقشه را بخوان، تأیید کن، پیاده کن.
۶. تست، commit، clear.
