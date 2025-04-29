<?php

namespace App\Enums;

enum EmploymentType: string
{
    case COMMISSION = 'commission';
    case BASE_SALARY = 'base_salary';
    case HOURLY = 'hourly';
    case FREELANCE = 'freelance';
    case CONTRACT = 'contract';
}
