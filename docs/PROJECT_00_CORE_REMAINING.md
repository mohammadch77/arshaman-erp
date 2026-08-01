# پروژه کوچک ۳: تکمیل هسته — طرف‌حساب‌ها و ارز
## دو ردیف باقی‌مانده گروه الف (شرکت‌ها و کاربران قبلاً در PROJECT_01_AUTH.md تمام شدند)

> **چرا این سند لازم شد:** طبق `DECISIONS.md`، هنگام شروع فاز ۲ (HR) کشف کردیم که گروه الف
> (هسته) کامل نشده بود — فقط شرکت‌ها و کاربران/نقش‌ها ساخته شده بودند، نه طرف‌حساب‌ها و ارز/تقویم.
> تصمیم گرفتیم HR را (چون وابستگی به این دو نداشت) اول تمام کنیم، بعد برگردیم اینجا — این همان برگشتن است.

> **تفاوت این سند با `PROJECT_02_HR.md`:** سند طراحی برای این ماژول توضیح تفصیلی ندارد (فقط یک
> سطر خلاصه در جدول گروه الف)، پس تکه‌بندی Session‌ها بر پایه اصول کلی پروژه (`CLAUDE.md`,
> `DATABASE_CONVENTIONS.md`) طراحی شده، نه ترجمه مستقیم بخش‌بندی سند.

---

## چرا این دو ماژول واقعاً لازم‌اند — قبل از فاز ۳

فاز ۳ (محصولات، سفارش‌ها، انبار) به هر دوی این‌ها وابستگی سخت دارد:
- **سفارش نیاز به طرف‌حساب دارد** (فروش به کدام مشتری، خرید از کدام تأمین‌کننده)
- **قیمت‌گذاری چندارزی نیاز به نرخ ارز دارد** (طبق بند ۲.۲ سند: «ارز و تقویم: تضمین چندارزی بودن»)

بدون این دو، فاز ۳ عملاً قابل ساخت نیست.

## خبر خوب — نیمی از «تقویم» از قبل ساخته شده

در ماژول HR (`app/Support/Jalali.php`) یک لایه کامل تبدیل شمسی↔میلادی با متدهای `toGregorian`, `toJalaliParts`, `calendarDay`, `maxDayForMonth` (کبیسه‌آگاه) و کامپوننت‌های `<x-jalali-date-select>` و `<x-time-picker>` ساخته‌ایم. **این‌ها را دوباره نمی‌سازیم** — فقط برای «سال مالی» (فروردین تا اسفند) از همین زیرساخت استفاده می‌کنیم.

---

## تصمیم‌های معماری

### ۱. ارز پایه سیستم، تومان است — نه ارز پایه هر شرکت
`companies.base_currency` (از Session 1 Auth) فقط یک `VARCHAR(3)` نمایشی است. نرخ ارز واقعی که در این ماژول می‌سازیم، **همیشه به تومان (IRR)** تبدیل می‌کند — چون گزارش تجمیعی هلدینگ (بند ۲.۴ سند) «جمع ساده همه شرکت‌ها به تومان» است، نه چند ارز پایه مختلف.

### ۲. Snapshot نرخ ارز — از قبل در `CLAUDE.md` بند ۵.۲ تعهد شده
«نرخ ارز روز تراکنش کپی می‌شود» — این ماژول فقط جدول نرخ‌ها و سرویس resolve را می‌سازد؛ خودِ snapshot در ماژول سفارش (فاز ۳) اتفاق می‌افتد، اینجا فقط زیرساختش آماده می‌شود.

### ۳. طرف‌حساب می‌تواند هم مشتری هم تأمین‌کننده باشد
یک `party` می‌تواند هر دو نقش را داشته باشد (مثلاً یک تأمین‌کننده که گاهی هم از ما خرید می‌کند) — پس دو فلگ بولین جدا (`is_customer`, `is_supplier`)، نه یک enum انحصاری. **حداقل یکی باید true باشد** (constraint سطح دیتابیس + validation).

### ۴. سال مالی — فروردین تا اسفند، قابل بستن
طبق بند ۶.۴ سند («بستن دوره مالی و انتقال مانده‌ها»)، این‌جا فقط ساختار پایه (`fiscal_periods`) ساخته می‌شود؛ منطق واقعی بستن دوره و انتقال مانده در فاز ۶ (حسابداری کامل) خواهد بود. اینجا صرفاً محدوده‌های سال مالی (اول فروردین تا آخر اسفند هر سال شمسی) ثبت می‌شود تا ماژول‌های بعدی (سفارش، هزینه) بتوانند تراکنش را به یک سال مالی نسبت دهند.

---

## مدل داده (۳ جدول)

| جدول | نقش |
|---|---|
| `parties` | مشتری/تأمین‌کننده |
| `currencies` | فهرست ارزهای پشتیبانی‌شده (غیر از تومان) |
| `exchange_rates` | نرخ روزانه هر ارز به تومان |
| `fiscal_periods` | سال‌های مالی شمسی |

نام‌گذاری طبق `docs/DATABASE_CONVENTIONS.md`.

---

# تکه‌بندی به Session ها

## Session 1 — طرف‌حساب‌ها (Parties)

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_core_remaining_mysql.sql (جدول ۱: parties) را
بخوان. نقشه بده، بعد پیاده کن:

ماژول Core را با طرف‌حساب‌ها تکمیل کن:
1. Migration و model Party (در app/Modules/Core/Models): id، owner_company_id
   (BelongsToCompany)، name، is_customer، is_supplier (هر دو boolean،
   constraint سطح دیتابیس CHECK که حداقل یکی true باشد + validation
   سطح Laravel)، party_type (VARCHAR enum PHP: individual, company)،
   phone، email (nullable)، economic_code (nullable — کد اقتصادی برای
   اشخاص حقوقی)، address (nullable)، created_by_user_id/updated_by_user_id،
   soft delete.
2. Policy PartyPolicy: چه نقش‌هایی دسترسی دارند؟ (پیشنهاد: هر کاربری که
   در آن شرکت نقش دارد، می‌تواند ببیند؛ فقط holding_admin/accountant/operator
   می‌توانند بسازند/ویرایش کنند — طبق الگوی نقش‌های موجود ماژول Core).
3. Actions: CreatePartyRecord، UpdatePartyRecord — authorization داخل Action.
4. کامپوننت‌های Livewire: فهرست طرف‌حساب‌ها با جستجو (نام/موبایل) و فیلتر
   نوع (مشتری/تأمین‌کننده/هردو)، فرم ساخت/ویرایش.

تست: حداقل یکی از is_customer/is_supplier باید true باشد (رد در هر دو
لایه — دیتابیس و validation)؛ 403 برای نقش غیرمجاز؛ جستجو کار کند.

تمام وقتی: بتوانم مشتری/تأمین‌کننده بسازم، جستجو کنم، فهرستش را ببینم.
```

---

## Session 2 — ارز و نرخ ارز

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_core_remaining_mysql.sql (جدول ۲ و ۳: currencies
و exchange_rates) را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Currency: id، code (VARCHAR3، unique — مثل USD،
   EUR)، name، symbol، is_active.
2. Seeder: چند ارز رایج (دلار، یورو، درهم).
3. Migration و model ExchangeRate: id، currency_id، rate_to_toman
   (decimal — هر واحد ارز چند تومان)، effective_date، created_by_user_id.
4. سرویس ExchangeRateResolver (app/Modules/Core/Services): متد
   rate(string $currencyId, Carbon $date): decimal که نرخ همان تاریخ یا
   نزدیک‌ترین نرخ قبل از آن را برمی‌گرداند (طبق طراحی قدیمی سند: «نرخ برای
   تاریخی که نرخ مستقیم ندارد، آخرین نرخ قبلی را برگرداند»). اگر هیچ نرخی
   قبل از آن تاریخ نبود، Exception واضح بزند.
5. کامپوننت Livewire: ثبت نرخ روزانه (فرم ساده: ارز + نرخ + تاریخ شمسی
   با <x-jalali-date-select> موجود از HR) + فهرست تاریخچه نرخ‌ها.

تست: نرخ برای تاریخ بدون رکورد مستقیم، آخرین نرخ قبلی را برگرداند؛ اگر
هیچ نرخ قبلی نبود خطای واضح بدهد؛ authorization فقط holding_admin/accountant
برای ثبت نرخ (نه مشاهده — مشاهده برای همه نقش‌ها آزاد).

تمام وقتی: بتوانم نرخ روزانه ثبت کنم و ExchangeRateResolver برای هر
تاریخی نرخ درست برگرداند.
```

---

## Session 3 — سال مالی (Fiscal Periods)

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_core_remaining_mysql.sql (جدول ۴: fiscal_periods)
را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model FiscalPeriod: id، owner_company_id، name (مثلاً
   "سال مالی ۱۴۰۵")، start_date، end_date (اول فروردین تا آخر اسفند
   همان سال شمسی، محاسبه‌شده با app/Support/Jalali موجود از HR — از صفر
   نساز)، is_closed (boolean)، closed_at، closed_by_user_id (nullable).
2. Seeder: سال مالی جاری برای هر شش شرکت (بر پایه تاریخ امروز).
3. Action CreateFiscalPeriod و CloseFiscalPeriod (فعلاً فقط تغییر
   is_closed — منطق واقعی انتقال مانده‌ها در فاز ۶ اضافه می‌شود، اینجا
   فقط قفل ساختاری). authorization داخل Action (فقط holding_admin).
4. کامپوننت Livewire: فهرست سال‌های مالی + دکمه بستن (با تأییدیه، چون
   غیرقابل بازگشت است طبق طراحی).

تست: سال مالی نتواند دوباره بسته شود؛ فقط holding_admin بتواند ببندد؛
تاریخ شروع/پایان با تقویم شمسی درست محاسبه شود (اول فروردین، آخر اسفند
با در نظر گرفتن کبیسه).

نساز: منطق انتقال مانده حسابداری (فاز ۶).
تمام وقتی: بتوانم سال مالی هر شرکت را ببینم و ببندم؛ زیرساخت آماده
برای فاز ۶.
```

---

# ساختار نهایی ماژول

```
app/Modules/Core/
├── Models/
│   ├── Party.php
│   ├── Currency.php
│   ├── ExchangeRate.php
│   └── FiscalPeriod.php
├── Actions/
│   ├── CreatePartyRecord.php / UpdatePartyRecord.php
│   ├── RecordExchangeRate.php
│   └── CreateFiscalPeriod.php / CloseFiscalPeriod.php
├── Services/
│   └── ExchangeRateResolver.php
├── Policies/
│   └── PartyPolicy.php
└── Database/{Migrations,Seeders}/

app/Livewire/Core/
├── Parties/PartyIndex.php + PartyForm.php
├── Currency/ExchangeRateIndex.php
└── FiscalPeriod/FiscalPeriodIndex.php
```

---

# نکات حیاتی

۱. **تومان به‌عنوان ارز پایه سیستم** — قابل مذاکره نیست، چون گزارش تجمیعی هلدینگ روی همین فرض ساخته می‌شود.
۲. **از تقویم شمسی موجود HR استفاده کن، از صفر نساز** — `app/Support/Jalali.php` و `<x-jalali-date-select>` آماده‌اند.
۳. **این ماژول فقط زیرساخت است** — snapshot نرخ ارز واقعی در فاز ۳ (سفارش) و منطق کامل بستن سال مالی در فاز ۶ (حسابداری) اتفاق می‌افتد.
۴. **قانون‌های همیشگی پروژه دست‌نخورده:** authorization داخل Action، migrate بدون fresh روی دیتابیس اصلی، `owner_company_id`/`created_by_user_id` طبق قرارداد، Skill `mary-ui-component` قبل از هر Blade.

---

# اولین قدم

۱. اگر schema مرجع (`schema_core_remaining_mysql.sql`) هنوز در `docs/` نیست، اول آن را بساز (به کمک من، مثل کاری که برای HR کردیم) و در دو جا (سیستم + پنل Files) بگذار.
۲. `/clear` بزن.
۳. پرامپت Session 1 (طرف‌حساب‌ها) را بزن.
۴. طبق قانون همیشگی، نقشه/نتیجه را برایم بفرست قبل از commit.
