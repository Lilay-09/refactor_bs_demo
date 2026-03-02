<?php

namespace App\Enums;

enum TransactionType: string
{
    //
    case TRANSFER_IN = 'in';
    case TRANSFER_OUT = 'out';

    case ABA_PAYOUT = 'aba_payout';
    case INTERNAL = 'internal';

    public function label(): string{
        return match($this){
            self::TRANSFER_IN => 'in',
            self::TRANSFER_OUT => 'out'
        };
    }

    public static function options():array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'name' => $case->label(),
            ],
            [self::TRANSFER_IN,self::TRANSFER_OUT]
        );
    }
}
