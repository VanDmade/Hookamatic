<?php

namespace VanDmade\Hookamatic\Enums\ReportTypes;

enum Outbound: string
{

    case SUMMARY = 'summary';
    case TOTALS = 'totals';
    case TOTALS_BY_SUBSCRIBER = 'totals_by_subscriber';
    case TOTALS_BY_EVENT_TYPE = 'totals_by_event_type';

}
