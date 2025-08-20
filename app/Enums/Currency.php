<?php

namespace App\Enums;

enum Currency:string
{
    //
    case USD = 'USD';
    case KHR = 'KHR';

    public function label(): string{
        return match($this){
            self::USD => 'KHR',
            self::KHR => 'USD'
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
