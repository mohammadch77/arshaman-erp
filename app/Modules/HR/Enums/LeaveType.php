<?php

namespace App\Modules\HR\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Unpaid = 'unpaid';
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'استحقاقی',
            self::Sick => 'استعلاجی',
            self::Unpaid => 'بدون‌حقوق',
            self::Hourly => 'ساعتی',
        };
    }

    /**
     * مرخصی ساعتی روز-محور نیست: کارمند همان روز سرِ کار بوده و فقط چند ساعت
     * غایب بوده. هر جای کد که روز می‌شمارد (جمع ماهانه، کسر حقوق) باید این نوع
     * را کنار بگذارد، وگرنه یک مرخصی دو ساعته یک روز کامل حساب می‌شود.
     */
    public function isHourly(): bool
    {
        return $this === self::Hourly;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => ['id' => $case->value, 'name' => $case->label()])
            ->values()
            ->all();
    }
}
