<?php

namespace App\Enums;

enum PaymentStatus:int
{
    //
    case PENDING = 1;
    case PARTIAL = 2;
    case DONE = 3;
    case CANCEL = 4;
    case DELETE = 5;
}
