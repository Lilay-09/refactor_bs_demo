<?php

namespace App\Enums;

enum TrackingStatus:int
{
    //
    case AVAILABLE_FOR_PICK = 1;
    case PICKED = 2;
    case ACCEPTED_FOR_PICKUP = 3;
    case PICKED_AND_BOOKED = 4;
    case AT_WAREHOUSE = 5;
    case ON_DELIVERY = 6;
    case PENDING_PICK = 7;
    case DELAYED = 8;

    case DELIVERED = 9;

    case FAILED = 10;

    case RETURNING = 11;

    case PENDING_DEL = 12;

    case ACCEPTED_FOR_PICKUP_DEL = 13;

    case ON_DELIVERY_TRIP = 14;

    case ALL_COMPLETED = 15;

    case DONE_TRIP = 16;

    case FAILED_TRIP = 17;

    case CANCELED_DEL = 18;

    case FAILED_WITH_FEE = 19;

    case CANCELD_PICK = 20;

    case DROPPED_PICK = 21;

    case IN_TRANSIT = 22;

    case RETURNED = 23;


    public static function returnable():array{
        return [
            self::FAILED->value,
            self::FAILED_WITH_FEE->value,
            self::AT_WAREHOUSE->value,
        ];
    }
}
