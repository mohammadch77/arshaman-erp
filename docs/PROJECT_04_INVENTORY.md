# پروژه کوچک ۵: عملیاتی و انبار (گروه ب)
## محصولات، سفارش‌ها، انبار چندگانه، ارسال — فاز ۳

> این سند دقیقاً طبق بخش «گروه ب» و بخش ۴ سند طراحی (نسخه ۴.۰) نوشته شده. برخلاف CRM، این
> بخش سند طراحی جزئیات کامل و دقیق دارد — پس کمتر جای تصمیم خودسرانه باقی می‌ماند.

---

## دو تصمیم باز که باید همین الان جواب بدهی

### ۱. ارزش‌گذاری موجودی: میانگین موزون یا FIFO؟
سند طراحی هر دو را گفته («بر پایه میانگین موزون یا FIFO») و بین آن‌ها انتخاب نکرده. این یک تصمیم واقعی حسابداری است که مستقیم روی محاسبه سود هر سفارش اثر می‌گذارد:
- **میانگین موزون (Weighted Average):** ساده‌تر برای پیاده‌سازی، هر خرید جدید میانگین بهای کل موجودی را عوض می‌کند.
- **FIFO:** دقیق‌تر برای تورم بالا (وضعیت اقتصاد ایران)، ولی نیاز به نگهداری «لایه‌های خرید» جدا دارد (پیچیدگی بیشتر در کد و کوئری).

**پیشنهاد من: میانگین موزون** برای فاز اول — چون پیاده‌سازی امن‌تر و کم‌خطرتری دارد، و FIFO را می‌توان بعداً (اگر واقعاً لازم شد) به‌عنوان یک قابلیت پیشرفته اضافه کرد بدون تغییر ساختار جدول‌ها (فقط تغییر نحوه محاسبه در `Action`).

### ۲. چندانباری — دقیقاً چه معماری‌ای می‌خواهی؟
سند فقط می‌گوید «انبار فیزیکاً مشترک، موجودی به تفکیک شرکت مالک». طبق درخواستت، من این‌طور طراحی می‌کنم: **چند انبار فیزیکی نام‌دار** (نه فقط یک انبار فرضی) — مثلاً «انبار مرکزی تهران»، «انبار کرج» — که هرکدام می‌توانند موجودی چند شرکت را هم‌زمان نگه دارند. اگر تصویر متفاوتی در ذهنت است، همین الان بگو.

---

## معماری دولایه‌ای که این ماژول را متفاوت می‌کند

طبق سند طراحی بخش ۲.۳: «عملیات (سفارش) + انبار (موجودی) + مالی (بهای تمام‌شده) — یک فیلد مشترک بین این سه، بهای تمام‌شده است.» یعنی این ماژول تنها بخشی از پروژه است که **سه حوزه را هم‌زمان لمس می‌کند** — باید با احتیاط کامل ساخته شود.

---

## تصمیم‌های معماری (سخت‌گیرانه، طبق درخواستت)

### ۱. `warehouses` بین‌شرکتی است — سومین مورد این الگو در پروژه
دقیقاً مثل `holidays` (HR) و `contacts` (CRM): انبار فیزیکی متعلق به یک شرکت خاص نیست، پس **بدون `owner_company_id`**. مالکیت موجودی (نه خودِ انبار) در جدول `stocks` مشخص می‌شود.

### ۲. موجودی هرگز مستقیم آپدیت نمی‌شود — فقط از طریق دفترچه حرکت (Ledger)
دقیقاً همان الگویی که در Session 3 حضور و غیاب HR یاد گرفتیم (تردد جمع‌شونده، نه رکورد مستقیم). `stocks.quantity_on_hand` یک ستون **محاسبه‌شده و کش‌شده** است؛ منبع حقیقت واقعی `stock_movements` است. هر تغییر موجودی (خرید، فروش، مرجوعی، ضایعات، انبارگردانی) یک رکورد **مثبت** در `stock_movements` می‌سازد؛ جهت از `movement_type` مشخص می‌شود، نه از علامت عدد.

### ۳. موجودی هرگز منفی نمی‌شود — دفاع دولایه (الگوی تثبیت‌شده پروژه)
- **سطح Action:** قبل از هر خروج موجودی (`IssueStock`)، چک می‌کند موجودی کافی هست.
- **سطح دیتابیس:** `CHECK (quantity_on_hand >= 0)` روی `stocks` — دقیقاً همان الگوی `parties.chk_parties_role` و `employees.national_id`.

### ۴. Snapshot سه‌گانه در `order_lines` — طبق تعهد قبلی `CLAUDE.md` بند ۵.۲
`unit_sale_price_amount`, `unit_cost_amount`, `fulfillment_type` همه **کپی لحظه فروش** می‌شوند، نه reference زنده به `products`. این دقیقاً همان تعهدی است که از روز اول در `CLAUDE.md` نوشته شده بود و حالا اولین‌بار واقعاً پیاده می‌شود.

### ۵. Idempotency ووکامرس — طبق تعهد قبلی `CLAUDE.md` بند ۵.۴
`UNIQUE(owner_company_id, source, external_order_id)` — همگام‌سازی دوباره، سفارش تکراری نمی‌سازد.

### ۶. دو چرخه وضعیت سفارش — طبق بخش ۴.۳ سند
```
سفارش با قلم فیزیکی:  received → paid → preparing → shipped → delivered → closed
                                                              ↘ cancelled / returned
سفارش تماماً دیجیتال:  received → paid → delivered_instant → closed
```
**قانون سفارش ترکیبی (Verifex):** اگر سفارش حداقل یک قلم فیزیکی داشته باشد، مسیر فیزیکی فعال می‌شود، حتی اگر قلم دیجیتالش زودتر تحویل شده باشد.

### ۷. کارت هوشمند دعانو — فعلاً خارج از دامنه
طبق سند: «این مورد به‌عنوان فرصت آینده ثبت شده، جزو دامنه فازهای اولیه نیست.» فقط بهای تمام‌شده ترکیبی (کارت خام + تراشه + چاپ + برنامه‌ریزی) باید در `cost_price` محصول لحاظ شود — این با ساختار عادی `products.cost_price` (که خودت وقتی محصول را می‌سازی مبلغ نهایی ترکیبی را وارد می‌کنی) قابل‌پوشش است، نیازی به جدول جدا نیست.

---

## مدل داده (۷ جدول)

| جدول | نقش | محدوده |
|---|---|---|
| `products` | محصولات و خدمات | شرکت |
| `warehouses` | انبارهای فیزیکی نام‌دار | **بین‌شرکتی** |
| `stocks` | موجودی هر محصول در هر انبار برای هر شرکت مالک | شرکت (ترکیبی با انبار) |
| `stock_movements` | دفترچه حرکت موجودی (منبع حقیقت) | شرکت |
| `orders` | سفارش‌ها | شرکت |
| `order_lines` | اقلام سفارش (با snapshot) | — (زیرمجموعه سفارش) |
| `shipments` | ارسال و پیگیری مرسوله | شرکت |

نام‌گذاری طبق `docs/DATABASE_CONVENTIONS.md`.

---

# تکه‌بندی به Session ها

## Session 1 — محصولات و خدمات

```
CLAUDE.md و docs/schema_inventory_mysql.sql (جدول ۱: products) را
بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Product: id، owner_company_id (BelongsToCompany)،
   name، sku (unique در سطح owner_company_id + sku، هم CHECK/UNIQUE
   دیتابیس هم Rule::unique محدود به شرکت — طبق الگوی تأییدشده
   national_id در HR)، fulfillment_type (VARCHAR enum PHP: physical,
   digital, service — طبق بند ۵.۳ CLAUDE.md، سطح محصول نه شرکت)،
   unit_of_measure (ENUM نیتیو: piece, kilogram, liter, meter, box —
   پیش‌فرض piece)، sale_price (decimal)، cost_price (decimal, nullable
   — ممکن است هنوز نامشخص باشد)، currency_id (FK واقعی به currencies
   — این ماژول از قبل ساخته شده، بر خلاف HR که موقع ساختش نبود)،
   woocommerce_product_id (nullable)، is_active،
   created_by_user_id/updated_by_user_id، soft delete. ENUM نیتیو روی
   fulfillment_type طبق قرارداد تازه ماژول‌های جدید.
2. Policy ProductPolicy: مشاهده برای هر نقشی در شرکت؛ ساخت/ویرایش فقط
   holding_admin/operator (طبق الگوی تثبیت‌شده در CRM).
3. Actions: CreateProduct، UpdateProduct — authorize داخل Action با
   متد مرکزی User::hasRoleInCompany().
4. کامپوننت‌های Livewire: فهرست محصولات (فیلتر نوع تحویل، هشدار بصری
   اگر cost_price خالی است)، فرم ساخت/ویرایش.

تست: یکتایی sku در دو لایه؛ 403 برای نقش غیرمجاز؛ هشدار cost_price
خالی نمایش داده شود؛ authorization با دو تست رگرسیون امنیتی استاندارد.

تمام وقتی: بتوانم محصول با نوع تحویل و بهای تمام‌شده بسازم، فهرستش را
ببینم، هشدار محصولات بدون بهای تمام‌شده را ببینم.
```

---

## Session 2 — انبارهای فیزیکی و ساختار موجودی

```
CLAUDE.md و docs/schema_inventory_mysql.sql (جدول ۲ و ۳: warehouses و
stocks) را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Warehouse (بین‌شرکتی، بدون BelongsToCompany —
   طبق الگوی Holiday/Contact، با کامنت صریح چرا): id، name، address
   (nullable)، is_active.
2. Migration و model Stock: id، warehouse_id، product_id،
   owner_company_id (این ستون شرکت مالک موجودی است، نه انبار)،
   quantity_on_hand (decimal، پیش‌فرض صفر، CHECK >= 0)، reorder_point
   (decimal, nullable)، average_cost (decimal, nullable — برای
   میانگین موزون). UNIQUE(warehouse_id, product_id, owner_company_id).
3. Policy WarehousePolicy (بین‌شرکتی، فقط holding_admin مدیریت انبار
   خودش را می‌سازد/می‌بندد) و StockPolicy (شرکت‌محور، مشاهده برای هر
   نقشی، ویرایش فقط از طریق حرکت‌ها نه مستقیم — Session بعد).
4. کامپوننت Livewire: فهرست انبارها (ادمین کل) + فهرست موجودی هر
   انبار به تفکیک شرکت مالک.

تست: quantity_on_hand هرگز منفی نشود (چک سطح مدل + دیتابیس، دو تست
جدا)؛ یک محصول در دو شرکت مختلف در همان انبار موجودی جدا داشته باشد.

نساز: حرکت موجودی (ثبت خرید/فروش) — Session بعد. فعلاً stocks را با
seeder یا مستقیم صفر می‌گذاریم.
تمام وقتی: بتوانم چند انبار بسازم، موجودی هر شرکت در هر انبار جدا دیده شود.
```

---

## Session 3 — دفترچه حرکت موجودی (Ledger)

```
CLAUDE.md و docs/schema_inventory_mysql.sql (جدول ۴: stock_movements)
را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model StockMovement: id، stock_id (FK به stocks)،
   movement_type (VARCHAR enum PHP: purchase_in, sale_out, return_in,
   adjustment_in, adjustment_out, waste_out)، quantity (decimal، همیشه
   مثبت، CHECK > 0)، unit_cost (decimal, nullable — فقط برای حرکت‌های
   ورودی، برای محاسبه میانگین موزون)، reference_note (text, nullable)،
   created_by_user_id، occurred_at.
2. Action ها: ReceiveStock (ورود — به‌روزرسانی average_cost با فرمول
   میانگین موزون طبق تصمیم بالا)، IssueStock (خروج — چک موجودی کافی
   قبل از هر خروج، رد با پیام فارسی اگر ناکافی بود)، AdjustStock
   (انبارگردانی/مغایرت، هم افزایشی هم کاهشی). همه در یک DB::transaction
   با lockForUpdate روی رکورد stocks مربوطه (جلوگیری از race condition
   — دقیقاً همان الگوی PunchAttendance در HR)، quantity_on_hand را
   داخل همان تراکنش به‌روز می‌کنند. هر سه Action صریح
   activity()->causedBy($actor)->performedOn($stock)->withProperties([...])
   ثبت می‌کنند — دقیقاً الگوی ReopenPayrollRun در HR؛ حرکت موجودی به‌همان
   اندازه حقوق حساس است و باید ردیابی کامل داشته باشد.
3. کامپوننت‌های Livewire: فرم ثبت ورود/خروج/تعدیل + فهرست دفترچه حرکت
   یک محصول/انبار. هشدار بصری وقتی quantity_on_hand ≤ reorder_point.

تست: خروج بیشتر از موجودی رد شود (هم سطح Action هم CHECK دیتابیس با
insert مستقیم دورزننده Action)؛ میانگین موزون بعد از دو خرید با نرخ
متفاوت درست محاسبه شود؛ race condition دو خروج هم‌زمان (شبیه‌سازی با
قفل) موجودی را منفی نکند؛ هشدار نقطه سفارش مجدد نمایش داده شود.

تمام وقتی: بتوانم ورود/خروج/تعدیل موجودی ثبت کنم، میانگین موزون درست
باشد، و هرگز موجودی منفی نشود.
```

---

## Session 4 — سفارش‌ها (ساختار + ثبت دستی)

```
CLAUDE.md و docs/schema_inventory_mysql.sql (جدول ۵ و ۶: orders و
order_lines) را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Order: id، owner_company_id، party_id (FK به
   parties — مشتری)، order_status (VARCHAR enum PHP — همه مقادیر هر
   دو چرخه: received, paid, preparing, shipped, delivered, delivered_instant,
   closed, cancelled, returned)، source (VARCHAR enum: woocommerce,
   manual_instagram, manual_telegram, manual_other)، external_order_id
   (nullable)، exchange_rate_snapshot (decimal, nullable — طبق بند
   ۵.۲ CLAUDE.md)، currency_id (nullable، FK واقعی)، subtotal_amount،
   shipping_amount، total_amount، created_by_user_id/updated_by_user_id،
   soft delete. UNIQUE(owner_company_id, source, external_order_id)
   طبق بند ۵.۴ CLAUDE.md (idempotency).
2. Migration و model OrderLine: id، order_id، product_id، quantity
   (CHECK > 0)، unit_sale_price_amount (snapshot)، unit_cost_amount
   (snapshot, nullable)، fulfillment_type (VARCHAR، snapshot از
   product لحظه فروش — طبق بند ۵.۲/۵.۳)، line_total_amount.
3. Action CreateManualOrder: ثبت دستی سفارش (فقط source های manual_*)،
   snapshot سه‌گانه را از Product/ExchangeRateResolver می‌گیرد و کپی
   می‌کند، authorize داخل Action.
4. کامپوننت Livewire: فرم ثبت سفارش دستی (انتخاب مشتری، افزودن اقلام)،
   فهرست سفارش‌ها با فیلتر وضعیت/منبع.

تست: snapshot درست ثبت شود؛ تغییر بعدی sale_price محصول، سفارش قبلی
را عوض نکند (طبق الگوی تثبیت‌شده Payslip در HR)؛ idempotency (تلاش
برای ساخت دو سفارش با همان source+external_order_id رد شود).

نساز: ماشین وضعیت کامل (Session بعد)، همگام‌سازی ووکامرس (Session
بعد از آن).
تمام وقتی: بتوانم سفارش دستی با چند قلم بسازم و snapshot درست ثبت شود.
```

---

## Session 5 — ماشین وضعیت سفارش (دو چرخه + منطق ترکیبی)

```
CLAUDE.md بخش ۶ (ماشین‌های وضعیت) و docs/PROJECT_04_INVENTORY.md
(بخش «دو چرخه وضعیت سفارش») را بخوان. نقشه بده، بعد پیاده کن:

1. سرویس OrderStateMachine (app/Modules/Inventory/Services): نقشه
   ترنزیشن‌های مجاز برای هر دو چرخه (فیزیکی/دیجیتال) به‌صورت صریح
   (مثل نقشه TRANSITIONS در Lead ماژول CRM). متد
   hasPhysicalLine(Order $order): bool که مشخص می‌کند کدام چرخه
   اعمال شود (اگر حداقل یک OrderLine.fulfillment_type=physical بود،
   چرخه فیزیکی؛ وگرنه دیجیتال).
2. Action TransitionOrderStatus: ترنزیشن‌های نامعتبر رد می‌شوند
   (InvalidArgumentException با پیام فارسی)؛ authorize داخل Action.
   وقتی به preparing می‌رسد، IssueStock (Session 3) خودکار صدا زده
   می‌شود (کاهش موجودی). بعد از delivered/closed، فیلدهای مالی سفارش
   قفل می‌شوند (نگهبان مدل، طبق الگوی Payslip در HR). هر ترنزیشن صریح
   activity()->causedBy($actor)->performedOn($order)->withProperties(['from','to'])
   ثبت می‌کند.
3. کامپوننت Livewire: نمایش وضعیت فعلی + دکمه‌های ترنزیشن مجاز روی
   صفحه جزئیات سفارش.

تست: سفارش با قلم فیزیکی مسیر فیزیکی بگیرد؛ سفارش تماماً دیجیتال مسیر
کوتاه بگیرد؛ سفارش ترکیبی (Verifex) مسیر فیزیکی بگیرد حتی اگر قلم
دیجیتالش delivered_instant شود؛ ترنزیشن نامعتبر رد شود؛ رسیدن به
preparing موجودی را کم کند؛ بعد از closed، ویرایش مبلغ رد شود.

تمام وقتی: چرخه وضعیت هر دو نوع سفارش درست کار کند، اتصال به موجودی
خودکار باشد، قفل مالی بعد از بسته‌شدن برقرار باشد.
```

---

## Session 6 — همگام‌سازی ووکامرس

```
CLAUDE.md بخش ۵.۴ (idempotency) را بخوان. نقشه بده، بعد پیاده کن:

1. Job SyncWooCommerceOrders (صف‌بندی‌شده، per company): برای هر
   شرکتی که woocommerce_config دارد (از Session 1 Auth)، سفارش‌های
   جدید را از REST API می‌خواند.
2. اگر محصولی در سفارش وووکامرسی در سیستم نبود، خودکار با cost_price
   خالی ساخته شود + لاگ هشدار (نه خطا، طبق تصمیم قبلی طراحی).
3. Idempotency از قبل در Session 4 تضمین شده (UNIQUE constraint) —
   اینجا فقط از updateOrCreate روی همان کلید استفاده می‌شود، نه insert
   خام.
4. اگر API یک شرکت در دسترس نبود، لاگ کن و بقیه شرکت‌ها را متوقف نکن.
5. زمان‌بندی هر ۱۵ دقیقه (Laravel Scheduler).

تست: اجرای دوباره روی همان داده، سفارش تکراری نسازد؛ محصول ناشناخته
خودکار با هشدار ساخته شود؛ خطای یک شرکت روی بقیه اثر نگذارد.

⚠️ چون این Session به سرویس بیرونی (WooCommerce API) وابسته است و
کلید واقعی API هر پنج سایت لازم است، اگر هنوز در دسترس نیست، این
Session را با یک HTTP client قابل mock بساز و منتظر کلید واقعی بمان
(دقیقاً همان الگوی NotificationChannel شبیه‌سازی‌شده در CRM).
```

---

## Session 7 — ارسال و پیگیری مرسوله

```
CLAUDE.md و docs/schema_inventory_mysql.sql (جدول ۷: shipments) را
بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model Shipment: id، order_id، owner_company_id، carrier
   (VARCHAR، پیش‌فرض 'tipax' — طبق تصمیم کارفرما در DECISIONS.md، ولی
   ساختار برای شرکت حمل دیگر باز)، tracking_code (nullable تا ثبت
   شود)، status (VARCHAR enum: pending, packed, shipped, delivered)،
   shipped_at، delivered_at، shipping_cost_amount (decimal — بخشی از
   بهای تمام‌شده سفارش طبق بند ۴.۲ سند)، created_by_user_id.
2. Action ها: PackOrder، AssignTrackingCode (وضعیت سفارش را هم به
   shipped منتقل می‌کند، طبق اتصال Session 5)، MarkDelivered.
3. اطلاع‌رسانی: از همان NotificationChannel شبیه‌سازی‌شده CRM استفاده
   کن (send() فقط لاگ می‌کند) — طبق تصمیم مشترک، چون کلید API واقعی
   پیامک/تلگرام هنوز نیست.
4. کامپوننت Livewire: فرم بسته‌بندی + ثبت کد رهگیری + فهرست مرسولات
   در انتظار/ارسال‌شده.

تست: ثبت کد رهگیری، وضعیت سفارش را به shipped منتقل کند؛ shipping_cost_amount
در total_amount سفارش لحاظ شود؛ authorization طبق متد مرکزی.

تمام وقتی: بتوانم سفارش را بسته‌بندی کنم، کد رهگیری بدهم، تحویل را
ثبت کنم — و هزینه ارسال در بهای تمام‌شده سفارش دیده شود.
```

---

# ساختار نهایی ماژول

```
app/Modules/Inventory/
├── Models/
│   ├── Product.php
│   ├── Warehouse.php          ← بین‌شرکتی
│   ├── Stock.php
│   ├── StockMovement.php
│   ├── Order.php
│   ├── OrderLine.php
│   └── Shipment.php
├── Actions/
│   ├── CreateProduct.php / UpdateProduct.php
│   ├── ReceiveStock.php / IssueStock.php / AdjustStock.php
│   ├── CreateManualOrder.php
│   ├── TransitionOrderStatus.php
│   ├── PackOrder.php / AssignTrackingCode.php / MarkDelivered.php
│   └── SyncWooCommerceOrders.php (Job)
├── Services/
│   └── OrderStateMachine.php
├── Policies/
│   ├── ProductPolicy.php
│   ├── WarehousePolicy.php
│   ├── StockPolicy.php
│   ├── OrderPolicy.php
│   └── ShipmentPolicy.php
└── Database/{Migrations,Seeders}/

app/Livewire/Inventory/
├── Products/ProductIndex.php + ProductForm.php
├── Warehouses/WarehouseIndex.php + StockIndex.php
├── Movements/MovementForm.php + MovementLog.php
├── Orders/OrderIndex.php + OrderForm.php + OrderShow.php
└── Shipping/ShipmentIndex.php + PackForm.php
```

---

# نکات حیاتی

۱. **موجودی هرگز مستقیم آپدیت نمی‌شود** — فقط از طریق `ReceiveStock`/`IssueStock`/`AdjustStock`، هرکدام یک رکورد `stock_movements` می‌سازند.
۲. **موجودی هرگز منفی نمی‌شود** — دفاع دولایه (Action + `CHECK`).
۳. **Snapshot سه‌گانه در `order_lines` قابل مذاکره نیست** — این همان تعهدی بود که از روز اول در `CLAUDE.md` نوشته شده بود.
۴. **`warehouses` بین‌شرکتی، `stocks` شرکت‌محور** — این تفکیک را هرگز قاطی نکن.
۵. **race condition در حرکت موجودی** — همیشه با `lockForUpdate()` در `DB::transaction`، دقیقاً مثل `PunchAttendance` در HR.
۶. **Idempotency ووکامرس از روز اول در طراحی Order لحاظ شده**، نه چیزی که بعداً اضافه شود.

---

# اولین قدم

۱. تصمیم ارزش‌گذاری موجودی (میانگین موزون/FIFO) و تأیید معماری چندانباری را بده.
۲. `docs/schema_inventory_mysql.sql` را بسازیم (پیام بعدی).
۳. `/clear` بزن، Session 1 (محصولات) را شروع کن.
