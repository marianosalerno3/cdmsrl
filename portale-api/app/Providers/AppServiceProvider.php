<?php

namespace App\Providers;

use App\Services\Erp\ErpManager;
use App\Services\PriceService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PriceService::class);
        $this->app->singleton(ErpManager::class);
    }

    public function boot(): void
    {
        //
    }
}
