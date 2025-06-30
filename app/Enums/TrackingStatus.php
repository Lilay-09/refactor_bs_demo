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
    case DRAFT = 24;
    case RESERVE = 25;


    public static function returnable():array{
        return [
            self::FAILED->value,
            self::FAILED_WITH_FEE->value,
            self::AT_WAREHOUSE->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::AVAILABLE_FOR_PICK => __('messages.tracking.available_for_pick'),
            self::PICKED => __('messages.tracking.picked'),
            self::ACCEPTED_FOR_PICKUP => __('messages.tracking.accepted_for_pickup'),
            self::PICKED_AND_BOOKED => __('messages.tracking.picked_and_booked'),
            self::AT_WAREHOUSE => __('messages.tracking.at_warehouse'),
            self::ON_DELIVERY => __('messages.tracking.on_delivery'),
            self::PENDING_PICK => __('messages.tracking.pending_pick'),
            self::DELAYED => __('messages.tracking.delayed'),
            self::DELIVERED => __('messages.tracking.delivered'),
            self::FAILED => __('messages.tracking.failed'),
            self::RETURNING => __('messages.tracking.returning'),
            self::PENDING_DEL => __('messages.tracking.pending_del'),
            self::ACCEPTED_FOR_PICKUP_DEL => __('messages.tracking.accepted_for_pickup_del'),
            self::ON_DELIVERY_TRIP => __('messages.tracking.on_delivery_trip'),
            self::ALL_COMPLETED => __('messages.tracking.all_completed'),
            self::DONE_TRIP => __('messages.tracking.done_trip'),
            self::FAILED_TRIP => __('messages.tracking.failed_trip'),
            self::CANCELED_DEL => __('messages.tracking.canceled_del'),
            self::FAILED_WITH_FEE => __('messages.tracking.failed_with_fee'),
            self::CANCELD_PICK => __('messages.tracking.canceled_pick'),
            self::DROPPED_PICK => __('messages.tracking.dropped_pick'),
            self::IN_TRANSIT => __('messages.tracking.in_transit'),
            self::RETURNED => __('messages.tracking.returned'),
        };
    }
}
