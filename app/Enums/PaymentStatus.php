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

    public function label(){
        return match($this){
            self::PENDING => 'Pending',
            self::PARTIAL => 'Partial',
            self::DONE => 'Done'
        };
    }
}
