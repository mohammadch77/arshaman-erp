<?php

namespace App\Modules\HR\Enums;

enum ContractType: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case ProjectBased = 'project_based';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'دائم',
            self::Temporary => 'موقت',
            self::ProjectBased => 'پروژه‌ای',
        };
    }
}
