<?php

namespace App\Enums;

enum TransactionType: string
{
    //
    case TRANSFER_IN = 'in';
    case TRNASFER_OUT = 'out';
}
