# پروژه کوچک ۴: مدیریت ارتباط با مشتری (CRM)
## گروه و — به درخواست کارفرما، زودتر از فاز ۶

> **تغییر نقشه‌راه:** طبق سند طراحی، فقط ماژول ۲۲ (قیف فروش و سرنخ) فاز ۳ بود؛ بقیه فاز ۶.
> کارفرما خواسته **کل گروه CRM** را الان (زودتر از فاز ۶) بسازیم. این یک تصمیم آگاهانه با
> پیامدهای مستند است — نه انحراف بی‌دلیل از سند. جزئیات در `DECISIONS.md`.

---

## هشدار معماری مهم — قبل از شروع بخوان

نصف ماژول‌های این گروه به داده‌ای وابسته‌اند که **هنوز نداریم** (سفارش از فاز ۳، ارسال از فاز ۴، قرارداد از فاز ۵). طبق تصمیم مشترک، این ماژول‌ها را می‌سازیم ولی هرجا داده واقعی نیست، دقیقاً همان الگوی موفق `payslips.expense_posting_status` در HR را تکرار می‌کنیم: **ساختار کامل + TODO صریح + BACKLOG**، نه یک جدول جعلی که وانمود کند کار می‌کند.

| ماژول | وابستگی | چه چیزی الان واقعی است | چه چیزی TODO است |
|---|---|---|---|
| مخاطبین | فقط `Party` (اختیاری) | ✅ کامل | — |
| تیکتینگ | فقط مخاطبین | ✅ کامل | — |
| تعاملات | ثبت خودکار خرید ← سفارش (فاز ۳) | ثبت دستی کامل | تبدیل خودکار خرید به تعامل |
| قیف فروش | برای آرشامان: قرارداد (فاز ۵) | ساختار قیف/لید کامل | اتصال «لید برد شد → قرارداد» |
| RFM | تاریخچه خرید (فاز ۳) | جدول + Action محاسبه | تا سفارش نباشد، نتیجه خالی/صفر |
| کمپین/اتوماسیون | Telegram Bot API / پیامک (سرویس بیرونی) | ساختار + ارسال شبیه‌سازی‌شده (log) | اتصال API واقعی — کلید هنوز نیست |

---

## تصمیم‌های معماری

### ۱. معماری دولایه (بند ۸.۱ سند) — اولین جدول بین‌شرکتی پروژه
`contacts` (پروفایل واحد هلدینگ) **عمداً `BelongsToCompany` نمی‌گیرد** — دقیقاً همان استثنایی که برای `Holiday` در HR ساختیم (چون هدف، دیدن مشتری در سطح کل هلدینگ است، نه یک شرکت). `contact_site_profiles` (پروفایل هر سایت) `BelongsToCompany` می‌گیرد و به `contacts` وصل می‌شود.

### ۲. تصمیم کلیدی: شناسایی مشتری مشترک (بند ۸.۲)
وقتی یک `contact_site_profile` جدید ساخته می‌شود، سرویس `ContactMatcher` بر پایه **موبایل یا ایمیل** بین همه `contacts` می‌گردد؛ اگر تطابق پیدا شد، به همان `contact` (Golden Record) وصل می‌شود، وگرنه یک `contact` جدید ساخته می‌شود.

### ۳. ملاحظه حریم خصوصی — قابل مذاکره نیست
طبق سند: «فقط شناسه تماس (موبایل/ایمیل) و مبلغ خرید بین سایت‌ها به اشتراک گذاشته می‌شود، نه محتوای حساس سفارش‌ها.» یعنی از `contact` سطح هلدینگ فقط می‌شود نام/موبایل/ایمیل/جمع‌مبلغ خرید هر سایت را دید، **نه جزئیات سفارش‌ها**. این را در Policy سطح هلدینگ صریح رعایت می‌کنیم.

### ۴. تفاوت `Contact` با `Party` — این یک اشتباه رایج است، مراقب باش
`Party` (که در ماژول هسته ساختیم) برای **امور مالی** است (کیست که باید فاکتور بگیرد/بدهد). `Contact` برای **رابطه** است (کیست که باید باهاش تعامل/کمپین/تیکت داشته باشیم). یک مخاطب می‌تواند اختیاری به یک `Party` وصل شود (`contact_site_profiles.party_id`، nullable)، ولی این دو مفهوم جداند و نباید یکی شوند.

### ۵. قیف فروش هر سایت مجزاست
`leads.pipeline_stage` یک enum عمومی است (`new, contacted, qualified, proposal, won, lost`) که برای همه سایت‌ها یکسان تعریف می‌شود؛ تفاوت واقعی قیف‌ها (مثلاً آرشامان با چرخه طولانی‌تر) در **داده** (منبع، یادداشت، ارزش تخمینی) است، نه در ساختار جدول.

### ۶. کمپین — شبیه‌سازی‌شده (Log Driver)
طبق تصمیم گرفته‌شده، ارسال واقعی تلگرام/پیامک ساخته نمی‌شود. یک `NotificationChannel` سرویس با درایور `log` می‌سازیم — دقیقاً همان الگویی که در HR برای ایمیل دعوت‌نامه استفاده کردیم (`Mail::mailer('log')`). وقتی بعداً کلید API واقعی رسید، فقط درایور عوض می‌شود، نه کل معماری.

---

## مدل داده (۹ جدول)

| جدول | نقش | محدوده |
|---|---|---|
| `contacts` | پروفایل واحد هلدینگی (Golden Record) | **بین‌شرکتی** |
| `contact_site_profiles` | پروفایل هر سایت + لینک اختیاری به Party | شرکت |
| `interactions` | تایم‌لاین تماس/تلگرام/فرم/خرید | شرکت |
| `leads` | قیف فروش و سرنخ | شرکت |
| `rfm_segments` | نتیجه بخش‌بندی RFM | شرکت |
| `campaigns` | تعریف کمپین (قالب پیام + trigger) | شرکت |
| `campaign_logs` | تاریخچه ارسال (شبیه‌سازی‌شده) | شرکت |
| `tickets` | تیکت پشتیبانی | شرکت |
| `ticket_replies` | پاسخ‌های تیکت | — (زیرمجموعه ticket) |

نام‌گذاری طبق `docs/DATABASE_CONVENTIONS.md`.

---

# تکه‌بندی به Session ها

## Session 1 — مخاطبین (معماری دولایه)

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_crm_mysql.sql (جدول ۱ و ۲: contacts و
contact_site_profiles) را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Contact (بین‌شرکتی، بدون BelongsToCompany — طبق
   الگوی Holiday در HR، با کامنت صریح چرا): id، full_name، phone،
   email (nullable)، created_by_user_id/updated_by_user_id.
2. Migration و model ContactSiteProfile (BelongsToCompany): id،
   contact_id، owner_company_id، party_id (nullable، اختیاری لینک به
   Party مالی)، site_full_name (nullable — اگر نام محلی با نام هلدینگ فرق داشت)،
   first_seen_at، total_purchase_amount (decimal، فعلاً صفر — تا سفارش
   واقعی داشته باشیم)، created_by_user_id.
3. سرویس ContactMatcher (app/Modules/CRM/Services): متد
   findOrCreateContact(string $phone, ?string $email, string $companyId):
   Contact — بر پایه موبایل یا ایمیل در contacts موجود می‌گردد؛ اگر
   پیدا نشد Contact جدید می‌سازد.
4. Policy: ContactSiteProfilePolicy برای دسترسی سطح شرکت (هر نقشی که در
   شرکت دارد ببیند، فقط holding_admin/accountant/operator بسازند/ویرایش
   کنند). یک Policy جدا و محدودتر برای مشاهده سطح هلدینگ (فقط نام/موبایل/
   ایمیل/جمع‌مبلغ هر سایت — نه جزئیات سفارش، طبق ملاحظه حریم خصوصی سند).
5. کامپوننت‌های Livewire: فهرست مخاطبین شرکت جاری + فرم ساخت (که از
   ContactMatcher استفاده می‌کند)، و یک نمای «پروفایل ۳۶۰» ساده که پروفایل
   هلدینگی + پروفایل‌های هر سایت مخاطب را نشان دهد (بدون جزئیات سفارش).

تست: دو ContactSiteProfile با موبایل یکسان در دو شرکت مختلف، به یک
Contact وصل شوند (Golden Record)؛ نمای هلدینگی جزئیات سفارش نشان ندهد؛
403 برای نقش غیرمجاز.

تمام وقتی: بتوانم مخاطب بسازم، در شرکت دیگر همان موبایل را دوباره ثبت
کنم و ببینم به همان Golden Record وصل شد.
```

---

## Session 2 — تعاملات

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_crm_mysql.sql (جدول ۳: interactions) را بخوان.
نقشه بده، بعد پیاده کن:

1. Migration و model Interaction: id، contact_site_profile_id،
   owner_company_id، interaction_type (VARCHAR enum: call, telegram,
   site_form, purchase)، notes، occurred_at، created_by_user_id.
   ستون source_order_id (UUID، nullable، بدون FK — چون جدول orders
   هنوز نیست؛ کامنت صریح: // TODO: FK به orders وقتی فاز ۳ ساخته شد).
2. Action RecordInteraction: ثبت دستی (call/telegram/site_form).
   authorize داخل Action.
3. کامپوننت Livewire: تایم‌لاین تعاملات یک مخاطب (در همان صفحه پروفایل
   ۳۶۰ که در Session 1 ساختیم، تب یا بخش جدید).
4. متد استاتیک آماده برای آینده: Interaction::createFromOrder() —
   فقط امضا و کامنت TODO، بدون فراخوانی واقعی (چون سفارشی نیست که آن
   را صدا بزند).

تست: ثبت دستی تعامل کار کند؛ تایم‌لاین به ترتیب زمانی مرتب باشد.

نساز: تبدیل خودکار خرید — به BACKLOG.md اضافه کن (آیتم: «تبدیل خودکار
خرید به تعامل — وابسته به ماژول سفارش فاز ۳»).
```

---

## Session 3 — قیف فروش و سرنخ (Leads)

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_crm_mysql.sql (جدول ۴: leads) را بخوان. نقشه
بده، بعد پیاده کن:

1. Migration و model Lead: id، owner_company_id، contact_site_profile_id
   (nullable — سرنخ ممکن است هنوز مخاطب کامل نشده باشد)، source (VARCHAR:
   instagram, website, telegram, referral, other)، pipeline_stage
   (VARCHAR enum PHP: new, contacted, qualified, proposal, won, lost)،
   assigned_to_user_id (nullable)، estimated_value (decimal, nullable)،
   notes، created_by_user_id/updated_by_user_id. ستون contract_id
   (UUID، nullable، بدون FK — // TODO: اتصال به قرارداد وقتی فاز ۵ ساخته شد،
   فقط برای آرشامان معنا دارد).
2. Action ها: CreateLead، UpdateLeadStage (تغییر مرحله قیف، authorize
   داخل Action)، AssignLead.
3. کامپوننت Livewire: نمای Kanban یا فهرست ساده قیف (ستون به ازای هر
   pipeline_stage)، فرم ساخت لید، دکمه تغییر مرحله.

تست: تغییر مرحله لید کار کند؛ لید بدون مخاطب هم قابل‌ساخت باشد
(contact_site_profile_id nullable)؛ 403 برای نقش غیرمجاز.

نساز: اتصال «لید برد شد → قرارداد» — به BACKLOG.md اضافه کن.
تمام وقتی: بتوانم لید بسازم، مرحله‌اش را عوض کنم، به کسی تخصیص بدهم.
```

---

## Session 4 — بخش‌بندی RFM

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_crm_mysql.sql (جدول ۵: rfm_segments) را بخوان.
نقشه بده، بعد پیاده کن:

1. Migration و model RfmSegment: id، contact_site_profile_id،
   owner_company_id، recency_days (nullable)، frequency_count (nullable)،
   monetary_amount (nullable)، segment (VARCHAR enum PHP: vip, at_risk,
   dormant, new)، calculated_at.
2. Action CalculateRfmSegment: منطق محاسبه بر پایه interactions نوع
   purchase (که فعلاً دستی ثبت می‌شوند، چون سفارش خودکار نیست) — اگر
   هیچ تعامل خریدی برای آن مخاطب نبود، segment='new' و بقیه فیلدها null
   بماند (نه خطا، نه صفر گمراه‌کننده). authorize داخل Action.
3. کامپوننت Livewire: فهرست مخاطبین به تفکیک segment، دکمه «محاسبه دوباره».

تست: مخاطب بدون تعامل خرید → segment=new با فیلدهای null؛ مخاطب با چند
تعامل خرید دستی → محاسبه واقعی درست.

⚠️ یادآوری صریح در خروجی UI: «این بخش‌بندی بر پایه تعاملات دستی‌ثبت‌شده
است؛ دقتش وقتی سفارش‌های واقعی (فاز ۳) به‌طور خودکار ثبت شوند بالا می‌رود.»

تمام وقتی: ساختار و منطق محاسبه آماده باشد، حتی اگر داده فعلی کم باشد.
```

---

## Session 5 — کمپین و اتوماسیون (شبیه‌سازی‌شده)

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_crm_mysql.sql (جدول ۶ و ۷: campaigns و
campaign_logs) را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Campaign: id، owner_company_id، name، trigger_type
   (VARCHAR enum PHP: winback_90days, shipping_notification, cross_sell,
   welcome_first_purchase)، channel (VARCHAR: telegram, sms)،
   message_template (text)، is_active، created_by_user_id.
2. Migration و model CampaignLog: id، campaign_id، contact_site_profile_id،
   channel، status (VARCHAR: simulated — فقط این مقدار در این فاز)،
   payload (JSON — متن نهایی پیام)، sent_at.
3. سرویس NotificationChannel (app/Modules/CRM/Services): متد
   send(string $channel, string $to, string $message) — فعلاً فقط
   Log::info() می‌زند (شبیه‌سازی)، با کامنت صریح:
   // TODO: اتصال API واقعی تلگرام/پیامک وقتی کلید در دسترس بود.
4. Action TriggerWinbackCampaign: برای مخاطبینی که RfmSegment آن‌ها
   dormant است (یا ۹۰ روز از آخرین تعامل خرید گذشته)، پیام کمپین را
   می‌سازد و از طریق NotificationChannel «ارسال» (لاگ) می‌کند + رکورد
   CampaignLog می‌سازد. این فقط دستی/زمان‌بندی‌شده صدا زده می‌شود، نه
   واقعاً خودکار روی رویداد سیستمی (چون رویدادهای واقعی مثل «کد رهگیری
   ثبت شد» هنوز وجود ندارند).
5. کامپوننت Livewire: مدیریت کمپین‌ها (ساخت/ویرایش قالب) + فهرست
   CampaignLog (تاریخچه «ارسال‌های» شبیه‌سازی‌شده).

تست: TriggerWinbackCampaign فقط مخاطبین dormant را هدف بگیرد؛
CampaignLog با status=simulated ثبت شود، نه sent واقعی.

⚠️ برچسب صریح در UI: «حالت شبیه‌سازی — پیامی واقعاً ارسال نمی‌شود.»
```

---

## Session 6 — تیکتینگ

**پرامپت آماده:**
```
CLAUDE.md و docs/schema_crm_mysql.sql (جدول ۸ و ۹: tickets و
ticket_replies) را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Ticket: id، contact_site_profile_id،
   owner_company_id، subject، description، status (VARCHAR enum: open,
   in_progress, resolved, closed)، priority (VARCHAR enum: low, normal,
   high)، assigned_to_user_id (nullable)، created_by_user_id، soft delete.
2. Migration و model TicketReply: id، ticket_id، user_id، message،
   created_at (بدون updated — پاسخ‌ها ویرایش نمی‌شوند، طبق اصل ثبت
   بدون‌دستکاری).
3. Action ها: CreateTicket، ReplyToTicket، ChangeTicketStatus —
   authorize داخل Action.
4. کامپوننت Livewire: فهرست تیکت‌ها (فیلتر وضعیت/اولویت) + صفحه جزئیات
   تیکت با تایم‌لاین پاسخ‌ها (که در پروفایل ۳۶۰ مخاطب هم قابل‌مشاهده باشد).

تست: تغییر وضعیت تیکت کار کند؛ پاسخ به تیکت بسته هم امکان‌پذیر باشد
(برای پیگیری) ولی هشدار بدهد؛ 403 برای نقش غیرمجاز.

تمام وقتی: تیکت بسازم، پاسخ بدهم، وضعیتش را عوض کنم — و در پروفایل ۳۶۰
مخاطب هم دیده شود.
```

---

# ساختار نهایی ماژول

```
app/Modules/CRM/
├── Models/
│   ├── Contact.php                    ← بین‌شرکتی، بدون BelongsToCompany
│   ├── ContactSiteProfile.php
│   ├── Interaction.php
│   ├── Lead.php
│   ├── RfmSegment.php
│   ├── Campaign.php
│   ├── CampaignLog.php
│   ├── Ticket.php
│   └── TicketReply.php
├── Actions/
│   ├── CreateLead.php / UpdateLeadStage.php / AssignLead.php
│   ├── RecordInteraction.php
│   ├── CalculateRfmSegment.php
│   ├── TriggerWinbackCampaign.php
│   └── CreateTicket.php / ReplyToTicket.php / ChangeTicketStatus.php
├── Services/
│   ├── ContactMatcher.php
│   └── NotificationChannel.php        ← درایور log، بعداً API واقعی
├── Policies/
│   ├── ContactSiteProfilePolicy.php
│   └── HoldingContactViewPolicy.php   ← دسترسی محدود سطح هلدینگ
└── Database/{Migrations,Seeders}/

app/Livewire/CRM/
├── Contacts/ContactIndex.php + Profile360.php
├── Leads/LeadPipeline.php
├── Rfm/RfmIndex.php
├── Campaigns/CampaignIndex.php
└── Tickets/TicketIndex.php + TicketShow.php
```

---

# نکات حیاتی

۱. **`Contact` عمداً بین‌شرکتی است** — تنها استثنای دوم پروژه بعد از `Holiday` در HR. کامنت صریح در کد لازم است تا کسی اشتباهی `BelongsToCompany` رویش نگذارد.
۲. **حریم خصوصی سطح هلدینگ قابل مذاکره نیست** — فقط نام/موبایل/ایمیل/جمع‌مبلغ، هرگز جزئیات سفارش.
۳. **هر جا TODO گذاشتیم، در BACKLOG.md هم ثبت شود** — دقیقاً الگوی موفق HR.
۴. **کمپین فقط شبیه‌سازی است** — برچسب UI صریح تا کسی فکر نکند پیام واقعی رفته.
۵. **Contact ≠ Party** — این دو مفهوم را در کد و مستندات هرگز قاطی نکن.

---

# اولین قدم

۱. اول `docs/schema_crm_mysql.sql` را (مثل HR و Core) بسازیم.
۲. این تصمیم بزرگ (جابه‌جایی کل گروه CRM به فاز ۳ جدید) را در `DECISIONS.md` ثبت کنیم و ترتیب فازهای بعدی (که با آن جابه‌جا می‌شوند) را در `README_PROJECT.md`/`IMPLEMENTATION_PLAYBOOK.md` به‌روز کنیم.
۳. `/clear` بزن، Session 1 (مخاطبین) را شروع کن.
