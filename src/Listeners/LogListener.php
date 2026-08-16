<?php

namespace VanDmade\Hookamatic\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use VanDmade\Hookamatic\Events\HookamaticLog;

class LogListener
{

    public function handleLog(HookamaticLog $event): void
    {
        switch ($event->type) {
            case 'debug':
                Log::debug('Hookamatic: '.$event->message, $event->context);
                break;
            case 'warning':
                Log::warning('Hookamatic: '.$event->message, $event->context);
                break;
            case 'error':
                Log::error('Hookamatic: '.$event->message, $event->context);
                break;
            case 'info':
            default:
                Log::info('Hookamatic: '.$event->message, $event->context);
                break;
        };
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            HookamaticLog::class => 'handleLog',
        ];
    }

}
