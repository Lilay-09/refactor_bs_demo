<?php

namespace App\Enums;

enum BranchType: int
{
    //
    case HEAD_OFFICE = 1;

    public function label(): string{
        return match($this){
            self::HEAD_OFFICE => __('messages.b'.$this->value)
        };
    }

    public static function options():array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
