<?php

namespace App\Enums;

enum WarehouseStatus: int
{
    //
    case INACTIVE = 1;
    case ACTIVE = 2;

    public function label(): string{
        return match($this){
            self::INACTIVE => 'Inactive',
            self::ACTIVE => 'Active'
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
