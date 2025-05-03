<?php

namespace App\Enums;

enum PeriodType: String
{
    //
    case MONTHLY = 'monthly';
    case TERM = 'term';
    case SEMESTER = 'semester';
    case ANNUAL = 'annual';
}
