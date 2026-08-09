<?php

namespace VanDmade\Hookamatic\Console\Commands;

use Illuminate\Console\Command;
use VanDmade\Hookamatic\Events\HookamaticLog;

class HookamaticCommand extends Command
{

    public function error($string, $verbosity = null): void
    {
        if (app()->runningInConsole()) {
            parent::error($string, $verbosity);
        } else {
            HookamaticLog::dispatch('error', $string);
        }
    }

    public function line($string, $style = null, $verbosity = null): void
    {
        if (app()->runningInConsole()) {
            parent::line($string, $style, $verbosity);
        } else {
            HookamaticLog::dispatch('info', $string);
        }
    }

}
