<?php

namespace VanDmade\Hookamatic\Enums;

enum InboundStatus: string
{

    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

}
