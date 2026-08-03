<?php

namespace VanDmade\Hookamatic;

use Illuminate\Support\ServiceProvider;

class HookamaticServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hookamatic/config.php', 'hookamatic.config');
        $this->mergeConfigFrom(__DIR__.'/../config/hookamatic/outbound.php', 'hookamatic.outbound');
        $this->mergeConfigFrom(__DIR__.'/../config/hookamatic/inbound.php', 'hookamatic.inbound');
        $this->publishes([
            __DIR__.'/../config/hookamatic/config.php' => config_path('hookamatic/config.php'),
        ], 'hookamatic-config');
        $this->publishes([
            __DIR__.'/../config/hookamatic/outbound.php' => config_path('hookamatic/outbound.php'),
        ], 'hookamatic-outbound-config');
        $this->publishes([
            __DIR__.'/../config/hookamatic/inbound.php' => config_path('hookamatic/inbound.php'),
        ], 'hookamatic-inbound-config');
        $this->app->register(EventServiceProvider::class);
        $this->app->singleton(Hookamatic::class);
        $this->app->alias(Hookamatic::class, 'hookamatic');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
    }

}
