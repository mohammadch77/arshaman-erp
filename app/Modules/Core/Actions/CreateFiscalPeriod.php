<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\FiscalPeriod;
use App\Modules\Core\Models\User;
use App\Support\Farsi;
use App\Support\Jalali;
use Illuminate\Support\Facades\Gate;

class CreateFiscalPeriod
{
    /**
     * سال مالی یک شرکت را برای یک سال شمسی مشخص می‌سازد (اول فروردین تا آخر
     * اسفند همان سال — طول اسفند با Jalali::daysInMonth کبیسه‌آگاه است).
     */
    public function handle(string $ownerCompanyId, int $jalaliYear, User $actor): FiscalPeriod
    {
        Gate::forUser($actor)->authorize('create', [FiscalPeriod::class, $ownerCompanyId]);

        return FiscalPeriod::create(self::buildAttributes($ownerCompanyId, $jalaliYear));
    }

    /**
     * محاسبه محدوده تاریخ + نام سال مالی، جدا از authorize، تا seeder (که هیچ
     * کاربر واردشده‌ای ندارد) بتواند بدون عبور از Gate از همین منطق استفاده کند.
     *
     * @return array{owner_company_id: string, name: string, start_date: string, end_date: string}
     */
    public static function buildAttributes(string $ownerCompanyId, int $jalaliYear): array
    {
        $lastEsfandDay = Jalali::daysInMonth($jalaliYear, 12);

        return [
            'owner_company_id' => $ownerCompanyId,
            'name' => 'سال مالی '.Farsi::toDigits($jalaliYear),
            'start_date' => Jalali::toGregorian($jalaliYear, 1, 1),
            'end_date' => Jalali::toGregorian($jalaliYear, 12, $lastEsfandDay),
        ];
    }
}
