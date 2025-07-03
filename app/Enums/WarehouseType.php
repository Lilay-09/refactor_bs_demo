<?php

namespace App\Enums;

enum WarehouseType: int
{
    //
    case MAIN = 1;

    public function label(): string{
        return match($this){
            self::MAIN => 'Main Warehosue',
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
