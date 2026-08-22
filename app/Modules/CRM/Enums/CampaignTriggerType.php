<?php

namespace App\Modules\CRM\Enums;

enum CampaignTriggerType: string
{
    case Winback90Days = 'winback_90days';
    case ShippingNotification = 'shipping_notification';
    case CrossSell = 'cross_sell';
    case WelcomeFirstPurchase = 'welcome_first_purchase';

    public function label(): string
    {
        return match ($this) {
            self::Winback90Days => 'بازگشت مشتریان غیرفعال',
            self::ShippingNotification => 'اطلاع‌رسانی ارسال',
            self::CrossSell => 'فروش مکمل',
            self::WelcomeFirstPurchase => 'خوش‌آمدگویی خرید اول',
        };
    }
}
