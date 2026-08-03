<?php

namespace VanDmade\Hookamatic\Tests\Feature;

use VanDmade\Hookamatic\Hookamatic;
use VanDmade\Hookamatic\Tests\TestCase;

class ServiceProviderTest extends TestCase
{

    public function test_the_hookamatic_singleton_is_bound(): void
    {
        $this->assertInstanceOf(Hookamatic::class, $this->app->make('hookamatic'));
    }

}
