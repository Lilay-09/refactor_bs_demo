<?php

namespace App\Enums;

enum PaywayStatus:string
{
    //
    case PENDING = 'pending';
    case DONE = 'done';

    public function label(){
        return match($this){
            self::DONE => 'Done',
            self::PENDING => 'Pending'
        };
    }
}
