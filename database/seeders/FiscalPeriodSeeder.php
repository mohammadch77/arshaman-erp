<?php

namespace Database\Seeders;

use App\Modules\Core\Actions\CreateFiscalPeriod;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FiscalPeriod;
use Illuminate\Database\Seeder;
use Morilog\Jalali\Jalalian;

class FiscalPeriodSeeder extends Seeder
{
    /**
     * سال مالی جاری برای هر شرکت — بر پایه تاریخ اجرای seeder. مستقیم از
     * CreateFiscalPeriod::buildAttributes استفاده می‌کند (نه handle())، چون
     * seeder هیچ کاربر واردشده‌ای برای authorize ندارد — مثل الگوی CompanySeeder.
     */
    public function run(): void
    {
        $currentJalaliYear = Jalalian::now()->getYear();

        Company::query()->withoutGlobalScopes()->each(function (Company $company) use ($currentJalaliYear) {
            $attributes = CreateFiscalPeriod::buildAttributes($company->id, $currentJalaliYear);

            // withoutGlobalScopes: بدون آن، Global Scope خودکار owner_company (بر پایه
            // CompanyContext::id() که در seeder چون کاربری واردنشده null است) کوئری
            // انتخاب رکورد موجود را با شرط تناقض‌دار می‌کند و هر بار seed دوباره تکراری می‌سازد.
            FiscalPeriod::withoutGlobalScopes()->updateOrCreate(
                ['owner_company_id' => $company->id, 'name' => $attributes['name']],
                $attributes
            );
        });
    }
}
