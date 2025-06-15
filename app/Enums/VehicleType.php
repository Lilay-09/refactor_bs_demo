<?php

namespace App\Enums;

enum VehicleType: string
{
    //
    case MOTO = 'moto';
    case TUKTUK = 'tuktuk';
    case VAN = 'van';

    public function label(){
        return match($this){
            self::MOTO => __('messages.moto'),
            self::TUKTUK => __('messages.tuktuk'),
            self::VAN => __('messages.van')
        };
    }

    public static function options(){
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
