<?php

namespace App\Modules\CRM\Enums;

enum CampaignChannel: string
{
    case Telegram = 'telegram';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'تلگرام',
            self::Sms => 'پیامک',
        };
    }
}
