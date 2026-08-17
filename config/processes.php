<?php

use App\Modules\HR\Actions\ApproveLeave;
use App\Modules\HR\Actions\RejectLeave;
use App\Modules\HR\Models\Leave;

return [

    /*
    |--------------------------------------------------------------------------
    | رجیستری فرایندهای وصل‌شده به ماژول (subject_type)
    |--------------------------------------------------------------------------
    |
    | هر ماژولی که می‌خواهد مدل‌هایش بتوانند سوژه‌ی یک process_instance باشند،
    | باید اینجا کلاس مدل خودش را ثبت کند. process_definitions.subject_type فقط
    | مقداری از این لیست را می‌پذیرد — نه نام کلاس آزاد از ورودی کاربر، چون
    | subject_type مستقیم در یک FQCN استفاده می‌شود (خطر instantiate کلاس دلخواه).
    |
    | مثال (برنامه‌نویس، نه کاربر نهایی، این را پر می‌کند):
    | 'subject_types' => [
    |     \App\Modules\HR\Models\Leave::class,
    | ],
    |
    */

    'subject_types' => [
        Leave::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | برچسب فارسی هر subject_type برای پنل طراحی فرایند (holding_admin)
    |--------------------------------------------------------------------------
    |
    | فقط برای نمایش در select پنل ProcessDefinitionForm — خودِ subject_type
    | ذخیره‌شده در دیتابیس همچنان FQCN خام است، این کلید فقط برچسب است.
    |
    */

    'subject_type_labels' => [
        Leave::class => 'درخواست مرخصی (منابع انسانی)',
    ],

    /*
    |--------------------------------------------------------------------------
    | فیلدهای مجاز برای شرط مرحله‌ی condition (process_steps.condition_field)
    |--------------------------------------------------------------------------
    |
    | هر subject_type می‌تواند فهرست فیلدهای مجاز خودش را برای مقایسه‌ی شرطی
    | ثبت کند (کلید = FQCN همان subject_type بالا). condition_field فقط از این
    | whitelist پذیرفته می‌شود، نه یک نام ستون آزاد.
    |
    | مثال:
    | 'condition_fields' => [
    |     \App\Modules\HR\Models\Leave::class => ['days_count', 'leave_type'],
    | ],
    |
    */

    'condition_fields' => [
        Leave::class => ['days_count', 'leave_type'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action های مجاز برای اعمال نتیجه‌ی نهایی فرایند (whitelist کلاس)
    |--------------------------------------------------------------------------
    |
    | وقتی یک process_instance به approved/rejected می‌رسد، موتور اجرا باید یک
    | Action واقعی صدا بزند (مثلاً تغییر وضعیت مرخصی به تأییدشده). این Action فقط
    | از این لیست انتخاب می‌شود — هرگز از یک نام کلاس آزاد در ورودی کاربر
    | نمونه‌سازی نمی‌شود؛ همان الگوی امنیتی whitelist دامنه‌ی map/video در
    | ماژول SiteBuilder، اینجا برای instantiate کلاس.
    |
    | مثال:
    | 'result_actions' => [
    |     \App\Modules\HR\Models\Leave::class => [
    |         'approved' => \App\Modules\HR\Actions\ApproveLeave::class,
    |         'rejected' => \App\Modules\HR\Actions\RejectLeave::class,
    |     ],
    | ],
    |
    */

    'result_actions' => [
        Leave::class => [
            'approved' => ApproveLeave::class,
            'rejected' => RejectLeave::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | فیلدهای خلاصه‌ی سوژه برای پنل «کارهای من»/«درخواست‌های من» (بند ۴ CLAUDE.md)
    |--------------------------------------------------------------------------
    |
    | ماژول Process هرگز مستقیم مدل یک ماژول دیگر (مثل Leave) را import/query
    | نمی‌کند؛ این نگاشت داده‌محور (نه کد) تنها پلی است — کلید = FQCN همان
    | subject_type بالا، مقدار = نگاشت «مسیر dot-notation روی مدل سوژه» به
    | برچسب فارسی. مسیر می‌تواند یک رابطه را هم طی کند (مثل employee.full_name)
    | چون data_get() روی مدل Eloquent از طریق __get مقادیر و رابطه‌ها را یکسان
    | می‌خواند. اگر مقدار یک BackedEnum با متد label() بود، خودکار ->label()
    | نمایش داده می‌شود (نه مقدار خام enum).
    |
    */

    'subject_summary_fields' => [
        Leave::class => [
            'employee.full_name' => 'کارمند',
            'leave_type' => 'نوع مرخصی',
            'days_count' => 'تعداد روز',
            'start_date' => 'از تاریخ',
            'end_date' => 'تا تاریخ',
        ],
    ],

];
