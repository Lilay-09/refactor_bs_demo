<?php

namespace App\Enums;

enum PaymentMethod:string
{
    //
    case ABA = 'aba';
    case ACLEDA = 'acleda';
    case VATTANAC = 'vattanac';
    case COD = 'cod';
    case ABA_KHQR = 'aba_khqr';
    case ABA_APP = 'aba_app';

    public function label(){
        return match($this){
            self::ABA => 'ABA',
            self::ACLEDA => 'Acleda',
            self::VATTANAC => 'Vattanac',
            self::COD => 'COD',
            self::ABA_KHQR => 'ABA KHQR',
            self::ABA_APP => 'ABA APP'
        };
    }

    public static function optionsTransactionMethod(): array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            [
                self::ABA_APP,
                self::ABA_KHQR,
            ]
        );
    }

    public static function optionsMethod(): array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            [
                self::COD,
                // self::ABA_KHQR,
            ]
        );
    }

    public static function optionsBank(): array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            [
                self::ABA,
                self::ACLEDA,
                self::VATTANAC
            ]
        );
    }
}
