# پروژه کوچک ۲: منابع انسانی (HR)
## پرسنل، حضور و غیاب، مرخصی، حقوق و دستمزد — فاز ۲

> این ماژول طبق تصمیم صریح کارفرما (`DECISIONS.md`) بلافاصله بعد از هسته ساخته می‌شود،
> نه در جایگاه اصلی‌اش (بعد از عملیات/مالی) در سند طراحی نسخه ۳.۰.
> هر Session یک بخش، بعد تست و commit و clear — دقیقاً همان ریتم `PROJECT_01_AUTH.md`.

---

## تصمیم معماری مهم (قبل از شروع بخوان)

### ۱. اتصال حقوق↔هزینه به‌تعویق افتاده — این عمدی است، نه فراموشی
طبق سند طراحی، محاسبه حقوق باید خودکار در ماژول هزینه‌ها ثبت شود. اما چون ماژول هزینه‌ها (فاز ۴ جدید) هنوز ساخته نشده، این اتصال **در این پروژه پیاده نمی‌شود**. به‌جایش:
- هر فیش حقوقی یک ستون `expense_posting_status` دارد (`pending` | `posted`) که فعلاً همیشه `pending` می‌ماند.
- یک متد صریح `PayrollRun::pendingExpensePosting()` + کامنت `// TODO: اتصال به Finance/Expenses — نگاه کن BACKLOG.md #1` در کد گذاشته می‌شود.
- **هیچ جدول یا ماژول جعلی هزینه ساخته نمی‌شود.**

### ۲. اتصال به تایم‌شیت هم به‌تعویق افتاده — ولی این‌بار فایده جانبی دارد
طبق سند اصلی، نرخ ساعتی تایم‌شیت باید از حقوق محاسبه شود، ولی تایم‌شیت هنوز ساخته نشده (فاز ۵). چون HR (این پروژه) **قبل از** تایم‌شیت ساخته می‌شود، وقتی به فاز ۵ رسیدیم، این اتصال از روز اول واقعی خواهد بود — نیازی به دوره میانی «نرخ دستی موقت» نیست. فعلاً کاری برای این اتصال در این پروژه لازم نیست.

### ۳. کارمند لزوماً کاربر سیستم نیست
خیلی از کارمندان (مثلاً نیروی انبار یا تولید) هرگز وارد سامانه نمی‌شوند. پس:
- جدول `employees` یک موجودیت **مستقل** است، نه زیرمجموعه `users`.
- یک ستون `user_id` (nullable, FK به `users.id`) وجود دارد فقط برای کارمندانی که هم کاربر سیستم‌اند (مثلاً مدیر که هم کارمند است هم لاگین می‌کند).

### ۴. Snapshot در حقوق — همان اصل بخش ۵.۲ CLAUDE.md
حقوق پایه هر کارمند ممکن است تغییر کند، ولی فیش‌های قبلی نباید تغییر کنند. پس هر فیش حقوقی، حقوق پایه لحظه محاسبه را **کپی** می‌کند، نه reference زنده به `employees.base_salary`.

---

## این پروژه چطور «قابل توسعه» می‌ماند

- **منطق در Action ها**، نه در کامپوننت Livewire — همان الگوی ماژول Auth.
- **Authorization داخل خود Action** (نه فقط UI) — طبق قانون جدیدی که در Session 4 ماژول Auth کشف و در `CLAUDE.md` بخش ۹ ثبت شد. این‌جا خصوصاً برای محاسبه/نهایی‌کردن حقوق حیاتی است.
- **ماژول جدا:** مدل/Action/Policy در `app/Modules/HR`؛ کامپوننت‌های Livewire در `app/Livewire/HR`.

---

## مدل داده (۶ جدول)

| جدول | نقش |
|---|---|
| `employees` | پرونده پرسنلی (مستقل از `users`) |
| `holidays` | تعطیلات رسمی + تقویم کاری |
| `attendances` | ورود/خروج روزانه، تأخیر، اضافه‌کاری |
| `leaves` | درخواست مرخصی + گردش تأیید |
| `payroll_runs` | یک دوره محاسبه حقوق (مثلاً یک ماه، یک شرکت) |
| `payslips` | فیش حقوقی هر کارمند در یک `payroll_run` |

نام‌گذاری ستون‌ها طبق `docs/DATABASE_CONVENTIONS.md`: `owner_company_id`، `created_by_user_id`/`updated_by_user_id`، و در `leaves` چون هم درخواست‌دهنده هم تأییدکننده هر دو کاربرند: `approved_by_user_id` (نه `user_id` خام).

---

# تکه‌بندی به Session ها

## Session 1 — پرسنل (Employees)

**پرامپت آماده:**
```
CLAUDE.md را بخوان. سپس برای این بخش اول نقشه بده، هنوز کد نزن:

ماژول HR را شروع کن با:
1. Migration و model برای employees:
   id (UUID)، owner_company_id (FK companies، BelongsToCompany)،
   user_id (nullable FK به users — فقط اگر کارمند هم کاربر سیستم است)،
   full_name، national_id (unique)، position، hire_date،
   termination_date (nullable)، employment_status (VARCHAR enum PHP:
   active, on_leave, terminated)، base_salary (decimal)، currency_id
   (FK به currencies — اگر Session 4 ماژول Core ساخته شده؛ وگرنه فعلاً
   nullable و بدون FK تا آن ماژول ساخته شود)، created_by_user_id/
   updated_by_user_id، soft delete.
2. Policy: فقط holding_admin و accountant به این بخش دسترسی دارند
   (طبق نقش‌های seed‌شده در ماژول Core).
3. Action ها: CreateEmployee, UpdateEmployee, TerminateEmployee
   (منطق در Action، authorization داخل خود Action طبق قانون بخش ۹
   CLAUDE.md).
4. کامپوننت‌های Livewire: فهرست پرسنل (جدول Mary UI، فیلتر بر اساس
   وضعیت استخدام)، فرم ساخت/ویرایش.
5. تست: کاربر بدون نقش مجاز نتواند به فهرست پرسنل دسترسی داشته باشد (403)؛
   ساخت کارمند با موفقیت.

نساز: حضور و غیاب، مرخصی، حقوق — Session های بعدی.
تمام وقتی: بتوانم کارمند بسازم، ویرایش کنم، و فهرستش را ببینم.
```

---

## Session 2 — تقویم کاری و تعطیلات رسمی

**پرامپت آماده:**
```
CLAUDE.md را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model برای holidays: id، title، holiday_date،
   owner_company_id (nullable — اگر خالی یعنی تعطیلی سراسری برای همه
   شرکت‌ها، مثل تعطیلات رسمی کشور)، is_recurring_yearly (boolean،
   برای مناسبت‌هایی که هرسال تکرار می‌شوند مثل نوروز)، created_by_user_id.
2. Seeder: چند تعطیلی رسمی نمونه ایران (نوروز، ...).
3. سرویس WorkCalendar در app/Modules/HR/Services: متد
   isWorkday(Carbon $date, ?string $companyId): bool که جمعه‌ها و
   تعطیلات (سراسری + مخصوص شرکت) را در نظر بگیرد.
4. تست: تاریخ جمعه و تاریخ تعطیل رسمی هر دو isWorkday=false برگردانند؛
   یک روز عادی true برگرداند.

نساز: UI مدیریت تعطیلات (فعلاً فقط seeder کافی است) — اگر لازم شد بعداً
   اضافه می‌کنیم.
تمام وقتی: WorkCalendar::isWorkday() درست کار کند، چون حضور و غیاب و
   محاسبه حقوق در Session های بعدی به آن وابسته‌اند.
```

---

## Session 3 — حضور و غیاب (Attendance)

**پرامپت آماده:**
```
CLAUDE.md را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model برای attendances: id، employee_id، owner_company_id،
   attendance_date، check_in_at (nullable، timestamp)، check_out_at
   (nullable)، late_minutes (محاسبه‌شده)، overtime_minutes (محاسبه‌شده)،
   source (VARCHAR enum: manual, device — فعلاً فقط manual پیاده می‌شود)،
   created_by_user_id.
2. Action RecordAttendance: ثبت ورود/خروج، محاسبه تأخیر (بر اساس ساعت
   کاری استاندارد شرکت — فعلاً یک مقدار ثابت پیش‌فرض ۸ صبح در نظر بگیر،
   قابل تنظیم در فاز بعد)، محاسبه اضافه‌کاری. authorization داخل Action.
3. کامپوننت Livewire: فهرست حضور و غیاب یک کارمند (یا کل شرکت برای
   accountant)، فرم ثبت دستی ورود/خروج.
4. تست: ثبت ورود دیرتر از ۸ صبح، late_minutes درست محاسبه شود؛ ثبت خروج
   بعد از ساعت کاری استاندارد، overtime_minutes درست محاسبه شود؛ کاربر
   بدون نقش مجاز نتواند برای کارمند دیگری حضور ثبت کند.

نساز: مرخصی، حقوق — Session های بعدی.
تمام وقتی: بتوانم ورود/خروج یک کارمند را دستی ثبت کنم و تأخیر/اضافه‌کاری
   درست محاسبه شود.
```

---

## Session 4 — مرخصی‌ها (Leave)

**پرامپت آماده:**
```
CLAUDE.md را بخوان. نقشه بده، بعد پیاده کن:

1. Migration و model برای leaves: id، employee_id، owner_company_id،
   leave_type (VARCHAR enum: annual, sick, unpaid)، start_date، end_date،
   days_count (محاسبه‌شده بر اساس WorkCalendar::isWorkday — روزهای
   تعطیل/جمعه جزو مرخصی حساب نشوند)، leave_status (VARCHAR enum:
   pending, approved, rejected)، reason (nullable)، approved_by_user_id
   (nullable تا زمان تأیید)، created_by_user_id.
2. Action ها: RequestLeave (ثبت درخواست با leave_status=pending)،
   ApproveLeave و RejectLeave (فقط holding_admin یا accountant،
   authorization داخل Action).
3. کامپوننت‌های Livewire: فرم درخواست مرخصی، فهرست درخواست‌های در انتظار
   تأیید (برای مدیر)، دکمه تأیید/رد.
4. تست: کاربر عادی نتواند مرخصی خودش را تأیید کند؛ days_count روزهای
   جمعه/تعطیل را حساب نکند؛ فقط نقش مجاز بتواند تأیید/رد کند.

نساز: حقوق — Session بعد (که به مرخصی بدون‌حقوق برای کسر از حقوق نیاز دارد).
تمام وقتی: بتوانم درخواست مرخصی ثبت کنم و مدیر تأیید/رد کند.
```

---

## Session 5 — حقوق و دستمزد (حساس‌ترین بخش این ماژول)

> **توجه ویژه:** طبق بخش ۷ `SESSION_GUIDE.md`، این منطق مالی است — مدل قوی (Opus) و تست قبل از پیاده‌سازی الزامی است. کد این Session را خودت خط‌به‌خط بخوان، نه فقط گزارش نهایی را بپذیری.

**پرامپت آماده:**
```
CLAUDE.md را بخوان. برای این بخش نقشه بده و صریح بگو کدام تکه منطق مالی
است (طبق بخش ۷ SESSION_GUIDE.md، آن تکه‌ها را با مدل Opus و تست قبل از
پیاده‌سازی بساز)، سپس پیاده کن:

1. Migration و model برای payroll_runs: id، owner_company_id،
   period_month (VARCHAR، فرمت شمسی مثل "1405-04")، payroll_status
   (VARCHAR enum: draft, calculated, finalized)، calculated_at،
   calculated_by_user_id، finalized_at، finalized_by_user_id.
2. Migration و model برای payslips: id، payroll_run_id، employee_id،
   gross_salary_amount (snapshot از employees.base_salary لحظه محاسبه
   — طبق بخش ۵.۲ CLAUDE.md، snapshot نه reference)، overtime_amount
   (محاسبه‌شده از مجموع attendances.overtime_minutes آن دوره)،
   deduction_amount (فعلاً صفر یا دستی — بیمه/مالیات واقعی فاز بعد)،
   net_amount (gross + overtime - deduction)، currency_id،
   expense_posting_status (VARCHAR enum: pending, posted — همیشه
   pending می‌ماند در این Session، طبق تصمیم BACKLOG.md #1).
3. Action CalculatePayroll: برای یک شرکت و یک ماه، برای هر کارمند فعال
   یک payslip می‌سازد (gross از snapshot حقوق پایه + جمع اضافه‌کاری آن
   ماه از attendances). قابل اجرا فقط یک‌بار در حالت draft؛ اگر دوباره
   صدا زده شد، رکوردهای قبلی را جایگزین می‌کند نه تکرار. authorization
   داخل Action (فقط holding_admin یا accountant).
4. Action FinalizePayrollRun: payroll_status را به finalized تغییر
   می‌دهد؛ بعد از finalize، هیچ payslip آن run نباید قابل ویرایش باشد
   (قفل مالی، مشابه قانون "بعد از delivered فیلدهای مالی قفل‌اند" در
   بخش ۶ CLAUDE.md).
5. کامپوننت Livewire: صفحه اجرای محاسبه حقوق ماهانه + فهرست فیش‌های
   یک payroll_run + دکمه نهایی‌کردن.
6. متد PendingExpensePosting در PayrollRun model: کوئری همه payslip
   های expense_posting_status=pending را برمی‌گرداند — با کامنت صریح
   // TODO: اتصال به Finance/Expenses — نگاه کن BACKLOG.md #1
   هیچ چیز دیگری با این متد انجام نمی‌شود در این Session.

تست (قبل از پیاده‌سازی منطق محاسبه بنویس، طبق قانون TDD این بخش):
- محاسبه حقوق با snapshot درست کار کند؛ تغییر بعدی base_salary کارمند،
  فیش‌های قبلی را عوض نکند.
- بعد از finalize، تلاش برای ویرایش payslip رد شود.
- اضافه‌کاری از attendances درست جمع و به مبلغ تبدیل شود.
- کاربر بدون نقش مجاز نتواند محاسبه حقوق را اجرا یا نهایی کند.

تمام وقتی: بتوانم برای یک شرکت و یک ماه حقوق محاسبه کنم، فیش‌ها را ببینم،
   نهایی کنم، و بعد از نهایی‌شدن دیگر قابل ویرایش نباشند.
```

---

## Session 6 (پایانی) — گزارش پایه هزینه پرسنل

**پرامپت آماده:**
```
CLAUDE.md را بخوان. نقشه بده، بعد پیاده کن:

یک کامپوننت Livewire گزارش ساده: مجموع net_amount همه payslip های
finalized یک ماه، به تفکیک شرکت. این گزارش موقتی است — وقتی فاز مالی
(Finance/Expenses) ساخته شد، این عدد باید با PostPayrollToExpenses
(طبق BACKLOG.md #1) واقعاً به‌عنوان expense ثبت شود؛ فعلاً فقط نمایش
است، نوشتن در جدول دیگری نیست.

تست: عدد گزارش با جمع دستی payslip های همان ماه/شرکت برابر باشد.

تمام وقتی: مدیر بتواند ببیند این ماه چقدر هزینه حقوق داشته‌ایم، به
تفکیک شرکت.
```

---

# ساختار نهایی ماژول (بعد از همه Session ها)

```
app/Modules/HR/
├── Models/
│   ├── Employee.php
│   ├── Holiday.php
│   ├── Attendance.php
│   ├── Leave.php
│   ├── PayrollRun.php
│   └── Payslip.php
├── Actions/
│   ├── CreateEmployee.php
│   ├── UpdateEmployee.php
│   ├── TerminateEmployee.php
│   ├── RecordAttendance.php
│   ├── RequestLeave.php
│   ├── ApproveLeave.php
│   ├── RejectLeave.php
│   ├── CalculatePayroll.php
│   └── FinalizePayrollRun.php
├── Services/
│   └── WorkCalendar.php
├── Policies/
│   └── EmployeePolicy.php
├── Database/
│   ├── Migrations/
│   └── Seeders/
└── Tests/

app/Livewire/HR/
├── Employees/EmployeeIndex.php
├── Employees/EmployeeForm.php
├── Attendance/AttendanceIndex.php
├── Attendance/RecordForm.php
├── Leaves/LeaveRequestForm.php
├── Leaves/LeaveApprovalIndex.php
├── Payroll/PayrollRunIndex.php
└── Payroll/PayrollReport.php

resources/views/livewire/hr/
├── employees/employee-index.blade.php
├── employees/employee-form.blade.php
├── attendance/attendance-index.blade.php
├── attendance/record-form.blade.php
├── leaves/leave-request-form.blade.php
├── leaves/leave-approval-index.blade.php
├── payroll/payroll-run-index.blade.php
└── payroll/payroll-report.blade.php
```

---

# نکات حیاتی برای این ماژول

## ۱. Snapshot در حقوق — قابل مذاکره نیست
هرگز `payslips.gross_salary_amount` را به `employees.base_salary` reference نکن. همیشه لحظه محاسبه کپی شود.

## ۲. قفل مالی بعد از finalize
دقیقاً مثل قانون سفارش (`delivered` قفل می‌کند)، اینجا هم `finalized` باید قفل کند. یک تست صریح برای این باید وجود داشته باشد.

## ۳. Authorization داخل Action — بدون استثنا
طبق قانون کشف‌شده در Session 4 ماژول Auth، `CalculatePayroll` و `FinalizePayrollRun` باید مستقل از UI هم محافظت‌شده باشند — این دقیقاً همان سناریویی است که آن قانون برایش نوشته شد.

## ۴. هیچ اتصال جعلی به Expenses نساز
وسوسه نشو یک جدول ساده «هزینه» بسازی «فقط برای این‌که کامل به‌نظر برسد». طبق `DECISIONS.md`، این عمداً به فاز ۴ موکول شده.

---

# اولین قدم همین الان

۱. `CLAUDE.md`، `DATABASE_CONVENTIONS.md`، `BACKLOG.md`، `DECISIONS.md` را (که از قبل در `docs/` هستند) دوباره مرور کن تا مطمئن شوی تصمیم HR-زودتر و قرارداد نام‌گذاری تازه در ذهنت است.
۲. Claude Code را باز کن، بگو `/clear` (اگر context قبلی باقی مانده).
۳. پرامپت Session 1 (پرسنل) را بزن.
۴. نقشه را بخوان، اینجا برایم بفرست، تأیید بگیر، بعد بگو «پیاده کن».
