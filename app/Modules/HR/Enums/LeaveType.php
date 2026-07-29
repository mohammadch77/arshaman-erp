<?php

namespace App\Modules\HR\Enums;

enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'استحقاقی',
            self::Sick => 'استعلاجی',
            self::Unpaid => 'بدون‌حقوق',
        };
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
