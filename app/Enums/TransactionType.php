<?php

namespace App\Enums;

enum TransactionType: string
{
    //
    case TRANSFER_IN = 'in';
    case TRNASFER_OUT = 'out';

    public function label(): string{
        return match($this){
            self::TRANSFER_IN => 'in',
            self::TRNASFER_OUT => 'out'
        };
    }

    public static function options():array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'name' => $case->label(),
            ],
            self::cases()
        );
    }
}
