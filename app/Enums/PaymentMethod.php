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
    case ABAPAY_KHRQ = 'abapay_khqr';
    case ABAPAY_KHQR_DEEPLINK = 'abapay_khqr_deeplink';

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
                'description' => $case->description(),
            ],
            [
                self::ABA_KHQR,
            ]
        );
    }

    public function description(): string
    {
        return match ($this) {
            self::ABA_APP => 'Pay directly using the ABA mobile app.',
            self::ABA_KHQR => 'Scan to pay with any banking app',
            self::COD => 'Pay with cash upon delivery.',
        };
    }

    public static function optionsMethod(): array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            [
                self::COD,
                self::ABA_KHQR,
            ]
        );
    }

    public static function optionsBank(): array
    {
        $cases = [
            self::ABA,
            self::ACLEDA,
            self::VATTANAC
        ];

        return array_map(
            fn($case, $index) => [
                'id'    => $index + 1,
                'name'  => $case->label(),
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
            array_keys($cases)
        );
    }
}

