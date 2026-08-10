<?php

namespace VanDmade\Hookamatic;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use VanDmade\Hookamatic\Outbound\Signing\SignerInterface;
use VanDmade\Hookamatic\Outbound\Signing\HmacSigner;
use VanDmade\Hookamatic\Outbound\Retry\RetryPolicyInterface;
use VanDmade\Hookamatic\Outbound\Retry\BackoffRetryPolicy;
use VanDmade\Hookamatic\Middleware\VerifyInboundWebhook;
use VanDmade\Hookamatic\Models\EventType;
use VanDmade\Hookamatic\Models\Subscribers\Subscriber;
use VanDmade\Hookamatic\Console\Commands\InboundStatsCommand;
use VanDmade\Hookamatic\Console\Commands\OutboundStatsCommand;
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
        $router->bind('subscriber', function($value) {
            $subscriber = is_numeric($value) ? Subscriber::find($value) : Subscriber::where('slug', $value)->first();
            if (is_null($subscriber)) {
                throw new ModelNotFoundException();
            }
            return $subscriber;
        });
        $router->bind('eventType', function($value) {
            $eventType = EventType::find($value);
            if (is_null($eventType)) {
                throw new ModelNotFoundException();
            }
            return $eventType;
        });
        // Default: require login to manage subscribers/event types. Override this gate
        // in your own app for anything more specific (e.g. admin-only) instead of
        // changing the guard mechanism itself.
        Gate::define('manage-hookamatic', function ($user = null) {
            return $user !== null;
        });
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes.php');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'hookamatic');
        if ($this->app->runningInConsole()) {
            $this->commands([
                RetryFailedDeliveriesCommand::class,
                OutboundStatsCommand::class,
                InboundStatsCommand::class,
            ]);
        }
    }

}
