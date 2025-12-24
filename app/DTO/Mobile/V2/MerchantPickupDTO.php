<?php
namespace App\DTO\Mobile\V2;
class MerchantPickupDTO{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly int $qty,
        public readonly string $date,
        public readonly string $time,
        public readonly int $status_id, 
        public readonly string $status_code,
        public readonly string $vehicle_type,
        public readonly string $driver_name,
        public readonly string $driver_phone,
        public readonly ?string $product_type = null
    )
    {}
}