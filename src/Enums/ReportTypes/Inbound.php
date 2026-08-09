<?php

namespace VanDmade\Hookamatic\Enums\ReportTypes;

enum Inbound: string
{

    case SUMMARY = 'summary';
    case TOTALS = 'totals';
    case TOTALS_BY_PROVIDER = 'totals_by_provider';

}
