<?php

namespace App\Providers;

use App\Models\VarianteProdotto;
use App\Observers\VarianteProdottoObserver;
use App\Services\Channels\ChannelManager;
use App\Services\Erp\ErpManager;
use App\Services\PriceService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PriceService::class);
        $this->app->singleton(ErpManager::class);
        $this->app->singleton(ChannelManager::class);
    }

    public function boot(): void
    {
        VarianteProdotto::observe(VarianteProdottoObserver::class);
    }
}
