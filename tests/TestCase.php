<?php

namespace VanDmade\Hookamatic\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use VanDmade\Hookamatic\HookamaticServiceProvider;

abstract class TestCase extends Orchestra
{

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
    }

}
