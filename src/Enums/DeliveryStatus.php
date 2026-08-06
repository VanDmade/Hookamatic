<?php

namespace VanDmade\Hookamatic\Enums;

enum DeliveryStatus: string
{

    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case EXHAUSTED = 'exhausted';
    case PAUSED = 'paused';

}
