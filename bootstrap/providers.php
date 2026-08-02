<?php

use App\Modules\CRM\Providers\CrmServiceProvider;
use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\HR\Providers\HrServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    HrServiceProvider::class,
    CrmServiceProvider::class,
];
