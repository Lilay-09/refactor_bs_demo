<?php

namespace App\Enums;


enum PaymentStatus:int
{
    //
    case PENDING = 1;
    case PARTIAL = 2;
    case DONE = 3;
    case CANCELED = 4;
    case DELETED = 5;
    case REQUESTED = 6;
    case APPROVED = 7;
    case APPROVE_AND_SETTLE = 8;
    case DECLINED = 9;
    case SETTLED_USD_REMAINING_KHR = 10;
    case SETTLED_KHR_REMAINING_USD = 11;
    case UNPAID = 1000;

    public function label(){
        return match($this){
            self::PENDING => 'Pending',
            self::PARTIAL => 'Partial',
            self::DONE => 'Done',
            self::REQUESTED => 'Requested',
            self::APPROVED => 'Approved',
            self::DECLINED => 'Declined',
            self::CANCELED => 'Canceled',
            self::DELETED => 'Deleted',
            self::APPROVE_AND_SETTLE => 'Approved And Settled',
            self::SETTLED_USD_REMAINING_KHR => 'Settled USD, Remaining KHR',
            self::SETTLED_KHR_REMAINING_USD => 'Settled KHR, Remaining USD',
            self::UNPAID => 'Unpaid'

        };
    }

    public static function optionsRequestedSettle():array{
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            [
                self::REQUESTED,
                self::APPROVE_AND_SETTLE,
                self::SETTLED_USD_REMAINING_KHR,
                self::SETTLED_KHR_REMAINING_USD
            ]
        );
    }

    public static function optionsCurrency(){
        return Currency::options();
    }

    
}
