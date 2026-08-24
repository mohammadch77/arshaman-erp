<?php

namespace App\Modules\Sales\Services;

use App\Modules\Core\Models\Company;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * پوششی نازک روی REST API ووکامرس — طبق الگوی NotificationChannel ماژول CRM
 * (بند صریح docs/BACKLOG.md Session 6 ماژول Sales): یک سرویس تک‌مسئولیتی و
 * قابل‌تزریق که با Http::fake در تست کاملاً جایگزین می‌شود، نه کدنویسی پراکنده
 * در Job. چون کلید واقعی API پنج سایت هنوز در دسترس نیست، این کلاس فقط
 * قرارداد HTTP واقعی ووکامرس را پیاده می‌کند و منتظر کلید واقعی می‌ماند.
 */
class WooCommerceClient
{
    private const PER_PAGE = 50;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchOrders(Company $company, DateTimeInterface $after): array
    {
        $config = $company->woocommerce_config ?? [];

        if (empty($config['url']) || empty($config['key']) || empty($config['secret'])) {
            throw new RuntimeException("پیکربندی ووکامرس شرکت «{$company->name}» ناقص است.");
        }

        $response = Http::withBasicAuth($config['key'], $config['secret'])
            ->timeout(15)
            ->get(rtrim($config['url'], '/').'/wp-json/wc/v3/orders', [
                'after' => $after->format(DateTimeInterface::ATOM),
                'per_page' => self::PER_PAGE,
                'orderby' => 'date',
                'order' => 'asc',
            ])
            ->throw();

        return $response->json() ?? [];
    }
}
