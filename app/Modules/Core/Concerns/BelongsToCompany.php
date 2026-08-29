<?php

namespace App\Modules\Core\Concerns;

use App\Modules\Core\Services\CompanyContext;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('owner_company', fn ($query) => $query->where(
            'owner_company_id',
            app(CompanyContext::class)->id()
        ));

        // بدنه‌ی بلوکی عمداً به‌جای arrow function («fn (...) => ...») است: خروجی
        // ??= (خودِ مقدار owner_company_id، تقریباً همیشه غیر-null) اگر برگردانده
        // شود، Eloquent چون رویداد creating را با Dispatcher::until() صدا می‌زند
        // (اولین پاسخ غیر-null کل زنجیره را متوقف می‌کند)، همین برگشت بی‌ضرر به‌نظر
        // هر creating listener دیگرِ ثبت‌شده بعد از این trait را — مثلاً یک
        // static::creating در booted() خودِ مدل مقصد، مثل تنظیم created_at در
        // StockTransfer — بی‌صدا حذف می‌کند. کشف شد چون created_at جابجایی
        // موجودی همیشه NULL می‌ماند؛ ریشه یک باگ سراسری بود، نه فقط ماژول انبار.
        static::creating(function ($model) {
            $model->owner_company_id ??= app(CompanyContext::class)->id();
        });
    }
}
