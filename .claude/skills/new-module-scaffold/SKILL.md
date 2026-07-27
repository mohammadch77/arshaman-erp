---
name: new-module-scaffold
description: هر بار که یک ماژول کاملاً جدید در پروژه ERP آرشامان شروع می‌شود (مثلاً Sales، Inventory، Finance، Projects، HR، CRM طبق نقشه فازها) — چه کاربر بگوید «ماژول X را شروع کن» چه از دل یک Epic جدید در IMPLEMENTATION_PLAYBOOK.md بیرون بیاید — از این Skill برای ساخت ساختار پوشه استاندارد استفاده کن، پیش از نوشتن هر migration یا model. این کار جلوی ساختار ناهماهنگ بین ماژول‌ها را می‌گیرد.
---

# ساختار استاندارد یک ماژول جدید (Modular Monolith)

مرجع کامل در بخش ۴ فایل `CLAUDE.md` است. این Skill آن را به یک رویه گام‌به‌گام تبدیل می‌کند.

## قبل از هر چیز: بررسی وابستگی

طبق ترتیب Epic‌های بخش ۲ `IMPLEMENTATION_PLAYBOOK.md`، مطمئن شو ماژول‌هایی که این ماژول جدید به آن‌ها وابسته است (مثلاً Sales به Core و Catalog و Inventory وابسته است) از قبل ساخته شده‌اند. اگر نه، به کاربر بگو و پیشنهاد بده اول آن‌ها را بسازد.

## ساختار پوشه‌ای که باید ساخته شود

برای منطق کسب‌وکار (زیر `app/Modules/<NameOfModule>/`):
```
app/Modules/<Module>/
├── Models/
├── Actions/          # منطق کسب‌وکار — نه در کامپوننت Livewire، نه در مدل
├── States/           # فقط اگر ماشین وضعیت دارد
├── Policies/
├── Database/
│   ├── Migrations/
│   └── Seeders/
└── Tests/
```

برای لایه UI (طبق قرارداد کشف خودکار Livewire، جدا از `app/Modules`):
```
app/Livewire/<Module>/
resources/views/livewire/<module-kebab>/
```

## قواعد اجباری هنگام ساخت

- [ ] هر مدل عملیاتی (نه مدل‌های سراسری مثل Company/User) باید trait `BelongsToCompany` را داشته باشد — طبق بخش ۵.۱ CLAUDE.md. این تنها‌ترین نقطه‌ای است که فراموشی‌اش باگ امنیتی می‌شود، پس قبل از commit دوباره چک کن.
- [ ] شناسه‌ها UUID (`HasUuids`، `CHAR(36)`)، نه auto-increment.
- [ ] مبالغ `decimal:2` / `DECIMAL(18,2)` — هرگز float.
- [ ] `created_by` و `updated_by` روی همه رکوردهای عملیاتی.
- [ ] Soft delete (`deleted_at`) — هرگز حذف فیزیکی.
- [ ] این ماژول فقط از طریق Action یا Event با ماژول‌های دیگر حرف می‌زند؛ هرگز مدل ماژول دیگر را مستقیم query نکن (طبق «قانون وابستگی» بخش ۴ CLAUDE.md).

## چیزی که این Skill نباید انجام دهد

- کامپوننت Livewire/Blade نساز مگر کاربر صریحاً در تعریف Session خواسته باشد — طبق الگوی هر Session این پروژه، ساخت UI معمولاً یک قدم جدا و بعدی است.
- بیش از یک ماژول را هم‌زمان شروع نکن، حتی اگر هر دو در همین فاز باشند — قانون ۱ بخش ۹ CLAUDE.md.
