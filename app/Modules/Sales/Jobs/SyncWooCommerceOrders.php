<?php

namespace App\Modules\Sales\Jobs;

use App\Modules\Core\Models\Company;
use App\Modules\Sales\Actions\SyncWooCommerceOrder;
use App\Modules\Sales\Services\WooCommerceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * سینک سفارش‌های یک شرکت مشخص از ووکامرس — یک Job جدا به‌ازای هر شرکت (نه یک
 * Job برای کل هلدینگ). طبق بند ۴ صریح این Session، خطای یک شرکت هرگز نباید
 * بقیه‌ی شرکت‌ها را متوقف کند؛ چون هر شرکت Job مستقل خودش را دارد، این
 * ایزولاسیون رایگان از خودِ معماری صف می‌آید. با این حال خطا اینجا هم صریح
 * گرفته و لاگ می‌شود (نه فقط رهاشدن به failed_jobs) تا رفتار بدون نیاز به
 * queue worker واقعی هم قابل پیش‌بینی/تست باشد.
 */
class SyncWooCommerceOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $companyId) {}

    public function handle(WooCommerceClient $client, SyncWooCommerceOrder $syncOrder): void
    {
        $company = Company::withoutGlobalScopes()->find($this->companyId);

        if (! $company || empty($company->woocommerce_config)) {
            return;
        }

        try {
            $orders = $client->fetchOrders($company, now()->subDays(2));
        } catch (Throwable $exception) {
            Log::error('همگام‌سازی ووکامرس برای این شرکت با خطا مواجه شد.', [
                'owner_company_id' => $company->id,
                'company_name' => $company->name,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($orders as $wcOrder) {
            try {
                $syncOrder->handle($company, $wcOrder);
            } catch (Throwable $exception) {
                // هر سفارش مستقل پردازش می‌شود — خطای یک سفارش (مثلاً محصول/داده‌ی
                // ناقص) نباید بقیه‌ی سفارش‌های همان شرکت را متوقف کند.
                Log::error('پردازش یک سفارش ووکامرس با خطا مواجه شد — این سفارش رد شد.', [
                    'owner_company_id' => $company->id,
                    'external_order_id' => $wcOrder['id'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
