<?php

namespace App\Enums;

enum TransferStatus: int
{
    //
    case PENDING = 1;
    case IN_TRANSTI = 2;
    case DELIVERED = 3;
}
