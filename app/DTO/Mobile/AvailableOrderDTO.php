<?php
namespace App\DTO\Mobile;
use App\Models\Order;
class AvailableOrderDTO{
    public function __construct(
        public readonly int $id,
        public readonly string $qty,
        public readonly string $order_datetime,
        public readonly string $merchant_phone,
        public readonly string $merchant_name = '',
        public readonly ?string $remarks = '',
        public readonly ?string $warehouse_address = '',
        public readonly ?string $loc_lat = '',
        public readonly ?string $loc_lng = '',
        public readonly ?string $pickup_address = '',
    ) {}

    public static function fromModel(Order $order): self
    {
        return new self(
            id: $order->id,
            order_datetime:$order->order_datetime,
            qty: $order->qty,
            merchant_name: $order->merchant_name ?? '',
            merchant_phone: $order->merchant_phone ?? '',
            remarks:$order->remarks ?? '',
            warehouse_address:$order->warehouse_address ?? '',
            loc_lat:$order->loc_lat ?? '0',
            loc_lng:$order->loc_lng ?? '0',
            pickup_address: $order->pickup_address ?? '',
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
