<?php

namespace VanDmade\Hookamatic\Facades;

use Illuminate\Support\Facades\Facade;

class Hookamatic extends Facade
{

    protected static function getFacadeAccessor(): string
    {
        return 'hookamatic';
    }

}
