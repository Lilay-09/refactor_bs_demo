<?php

namespace App\Enums;

enum TransferStatus: int
{
    //
    case PENDING = 1;
    case IN_TRANSIT = 2;
    case DELIVERED = 3;

    public function label(){
        return match($this){
            self::PENDING => __('messages.transfer_pending'),
            self::IN_TRANSIT => __('messages.transfer_in_transit'),
            self::DELIVERED => __('messages.transfer_delivered')
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
