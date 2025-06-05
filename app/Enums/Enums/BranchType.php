<?php

namespace App\Enums\Enums;

enum BranchType: int
{
    //
    case HEAD_OFFICE = 1;

    public function label(): string{
        return match($this){
            self::HEAD_OFFICE => __('messages.b'.$this->value)
        };
    }
}
