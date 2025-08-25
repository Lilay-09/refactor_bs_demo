<?php

namespace App\Enums;

enum PaymentStatus:int
{
    //
    case PENDING = 1;
    case PARTIAL = 2;
    case DONE = 3;
    case CANCELED = 4;
    case DELETED = 5;
    case REQUESTED = 6;
    case APPROVED = 7;
    case APPROVE_AND_SETTLE = 8;
    case DECLINED = 9;

    public function label(){
        return match($this){
            self::PENDING => 'Pending',
            self::PARTIAL => 'Partial',
            self::DONE => 'Done',
            self::REQUESTED => 'Requested',
            self::APPROVED => 'Approved',
            self::DECLINED => 'Declined',
            self::CANCELED => 'Canceled',
            self::DELETED => 'Deleted',
            self::APPROVE_AND_SETTLE => 'Approved And Settled'

        };
    }
}
