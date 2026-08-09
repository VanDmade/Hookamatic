<?php

namespace VanDmade\Hookamatic;

use Illuminate\Support\ServiceProvider;
use VanDmade\Hookamatic\Outbound\Signing\SignerInterface;
use VanDmade\Hookamatic\Outbound\Signing\HmacSigner;
use VanDmade\Hookamatic\Outbound\Retry\RetryPolicyInterface;
use VanDmade\Hookamatic\Outbound\Retry\BackoffRetryPolicy;
use VanDmade\Hookamatic\Middleware\VerifyInboundWebhook;
use VanDmade\Hookamatic\Console\Commands\RetryFailedDeliveriesCommand;
use VanDmade\Hookamatic\EventServiceProvider;

class HookamaticServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hookamatic.php', 'hookamatic');
        $this->publishes([
            __DIR__.'/../config/hookamatic.php' => config_path('hookamatic.php'),
        ], 'hookamatic-config');
        $this->app->bind(SignerInterface::class, function($app) {
            return $app->make(
                config('hookamatic.outbound.signer', HmacSigner::class)
            );
        });
        $this->app->bind(RetryPolicyInterface::class, function($app) {
            return $app->make(
                config('hookamatic.outbound.retry_policy', BackoffRetryPolicy::class)
            );
        });
        $this->app->register(EventServiceProvider::class);
        $this->app->singleton(Hookamatic::class);
        $this->app->alias(Hookamatic::class, 'hookamatic');
    }

    public function boot(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('hookamatic', VerifyInboundWebhook::class);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        if ($this->app->runningInConsole()) {
            $this->commands([
                RetryFailedDeliveriesCommand::class,
            ]);
        }
    }

}
