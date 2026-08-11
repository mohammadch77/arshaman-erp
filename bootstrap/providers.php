<?php

use App\Modules\Blog\Providers\BlogServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\CRM\Providers\CrmServiceProvider;
use App\Modules\HR\Providers\HrServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\SiteBuilder\Providers\SiteBuilderServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    HrServiceProvider::class,
    CrmServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    BlogServiceProvider::class,
    SiteBuilderServiceProvider::class,
];
