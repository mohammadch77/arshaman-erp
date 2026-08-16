<?php

namespace Database\Seeders;

use App\Modules\SiteBuilder\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * حذف یک‌باره‌ی ردیف کاتالوگ ویجت icon (Session ۹) طبق نظر صریح کارفرما —
 * WidgetKey::Icon از enum حذف شده، پس دیگر نمی‌توان با enum به آن اشاره کرد؛
 * اینجا عمداً رشته خام 'icon' استفاده می‌شود. idempotent و بی‌خطر روی هر
 * محیطی: اگر ردیف از قبل حذف شده یا هرگز وجود نداشته، کاری انجام نمی‌شود.
 *
 * تصمیم: هیچ داده‌ی widget_tree موجودی (صفحه یا دمو) دست‌زده نمی‌شود — در
 * زمان این حذف هیچ صفحه یا دمویی از این ویجت استفاده نمی‌کرد (بررسی مستقیم
 * دیتابیس واقعی). اگر در آینده یک نود icon قدیمی‌تر جایی پیدا شود،
 * WidgetContentRenderer از قبل هر widget_key ناشناخته را بی‌صدا لاگ و حذف
 * می‌کند (همان مسیر امنی که برای هر کلید نامعتبر دیگر هم وجود دارد) — نیازی
 * به یک migration داده‌ی جداگانه نبود.
 */
class SiteBuilderRemoveIconWidgetSeeder extends Seeder
{
    public function run(): void
    {
        Widget::where('widget_key', 'icon')->delete();
    }
}
