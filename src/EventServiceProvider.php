<?php

namespace VanDmade\Hookamatic;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use VanDmade\Hookamatic\Listeners\LogListener;

class EventServiceProvider extends ServiceProvider
{

    protected $subscribe = [
        LogListener::class,
    ];

}
