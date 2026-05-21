<?php

use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationServiceProvider;

return [
    AppServiceProvider::class,
    AiServiceProvider::class,
    AuthServiceProvider::class,
    FortifyServiceProvider::class,
    IntegrationServiceProvider::class,
];
