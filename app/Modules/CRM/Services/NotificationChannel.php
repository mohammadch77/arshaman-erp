<?php

namespace App\Modules\CRM\Services;

use Illuminate\Support\Facades\Log;

/**
 * ارسال واقعی هیچ کانالی (تلگرام/پیامک) در این فاز وصل نیست — طبق تصمیم
 * صریح کارفرما (schema_crm_mysql.sql، نکته ۴: campaign_logs.status همیشه
 * 'simulated' است). این سرویس فقط جای اتصال واقعی آینده را نگه می‌دارد.
 */
class NotificationChannel
{
    public function send(string $channel, string $target, string $message): void
    {
        Log::info('[شبیه‌سازی ارسال کمپین CRM]', [
            'channel' => $channel,
            'target' => $target,
            'message' => $message,
        ]);
    }
}
