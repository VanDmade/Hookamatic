<?php

namespace VanDmade\Hookamatic\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use VanDmade\Hookamatic\HookamaticServiceProvider;

use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{

    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            HookamaticServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        // Testbench's bundled users/password_reset_tokens/sessions migration - the base
        // Hookamatic migrations build a real foreign key to auth.providers.users.model
        // (Illuminate\Foundation\Auth\User by default), so a real users table has to exist.
        $this->loadMigrationsFrom(default_migration_path());
    }

}
