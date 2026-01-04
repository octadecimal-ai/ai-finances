<?php

namespace App\Providers;

use App\Services\Banking\WFirmaService;
use Illuminate\Support\ServiceProvider;

class WFirmaServiceProvider extends ServiceProvider
{
    /**
     * Rejestruje serwisy w kontenerze.
     */
    public function register(): void
    {
        $this->app->singleton(WFirmaService::class, function ($app) {
            return new WFirmaService();
        });

        // Rejestruj alias dla łatwiejszego dostępu
        $this->app->alias(WFirmaService::class, 'wfirma');
    }

    /**
     * Bootstrap serwisów aplikacji.
     */
    public function boot(): void
    {
        //
    }
}

