<?php
namespace App\DTO\Mobile\V2;
abstract class BaseTrackingPackageDTO{
    public function __construct(
        public readonly int $package_id,
        public readonly string $code,
        public readonly string $receiver_phone,
        public readonly string $cod_usd,
        public readonly string $arrive_date,
        public readonly string $arrive_time,
        public readonly string $zone_name,
        public readonly int $status_id,
        public readonly string $status_code,
        public readonly ?string $fees = null,
        public readonly ?string $taxi_fee = null,
        public readonly ?string $cod_khr = "0",
        public readonly ?string $receiver_address = null,
        public readonly ?string $image = null,
        public readonly ?string $driver_name = null,
        public readonly ?string $driver_phone = null,
        public readonly ?string $remarks = null
    ){}
}