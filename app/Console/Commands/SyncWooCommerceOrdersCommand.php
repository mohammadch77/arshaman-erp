<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Company;
use App\Modules\Sales\Jobs\SyncWooCommerceOrders;
use Illuminate\Console\Command;

/**
 * هر ۱۵ دقیقه (routes/console.php) برای هر شرکتی که woocommerce_config دارد
 * یک Job مستقل صف‌بندی می‌کند — دقیقاً الگوی blog:publish-scheduled
 * (فرآیند هلدینگ‌محور، withoutGlobalScopes چون CompanyContext ای در CLI
 * وجود ندارد).
 */
class SyncWooCommerceOrdersCommand extends Command
{
    protected $signature = 'woocommerce:sync-orders';

    protected $description = 'برای هر شرکتی که پیکربندی ووکامرس دارد، یک Job همگام‌سازی سفارش جداگانه صف‌بندی می‌کند';

    public function handle(): int
    {
        $companies = Company::withoutGlobalScopes()
            ->whereNotNull('woocommerce_config')
            ->get();

        foreach ($companies as $company) {
            SyncWooCommerceOrders::dispatch($company->id);
            $this->info("صف‌بندی شد: {$company->name}");
        }

        if ($companies->isEmpty()) {
            $this->info('هیچ شرکتی پیکربندی ووکامرس ندارد.');
        }

        return self::SUCCESS;
    }
}
