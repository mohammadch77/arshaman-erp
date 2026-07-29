<?php

namespace Database\Seeders;

use App\Modules\HR\Models\Holiday;
use App\Support\Jalali;
use Illuminate\Database\Seeder;

/**
 * تعطیلات رسمی نمونه ایران — تعطیلات ثابت شمسی (بدون تعطیلات وابسته به تقویم قمری
 * مثل عید فطر که سال به سال جابه‌جا می‌شوند). تاریخ‌ها با app/Support/Jalali از شمسی
 * به میلادی تبدیل می‌شوند؛ چون is_recurring_yearly=true است، سال مبنا فقط برای
 * محاسبه اولیه لازم است، هر سال تکرار می‌شود.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $baseYear = 1404;

        $holidays = [
            ['title' => 'نوروز (روز اول)', 'month' => 1, 'day' => 1],
            ['title' => 'نوروز (روز دوم)', 'month' => 1, 'day' => 2],
            ['title' => 'نوروز (روز سوم)', 'month' => 1, 'day' => 3],
            ['title' => 'نوروز (روز چهارم)', 'month' => 1, 'day' => 4],
            ['title' => 'روز جمهوری اسلامی', 'month' => 1, 'day' => 12],
            ['title' => 'سیزده به در', 'month' => 1, 'day' => 13],
            ['title' => 'رحلت امام خمینی', 'month' => 3, 'day' => 14],
            ['title' => 'قیام پانزده خرداد', 'month' => 3, 'day' => 15],
            ['title' => 'پیروزی انقلاب اسلامی', 'month' => 11, 'day' => 22],
            ['title' => 'ملی‌شدن صنعت نفت', 'month' => 12, 'day' => 29],
        ];

        foreach ($holidays as $holiday) {
            Holiday::create([
                'owner_company_id' => null,
                'title' => $holiday['title'],
                'holiday_date' => Jalali::toGregorian($baseYear, $holiday['month'], $holiday['day']),
                'is_recurring_yearly' => true,
            ]);
        }
    }
}
