<?php

use App\Core\Modules\ModuleServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TenancyServiceProvider::class,
];
