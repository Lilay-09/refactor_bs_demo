<?php
namespace App\DTO\Mobile\V2;
class TrackingFailPackageDTO extends BaseTrackingPackageDTO {
    public function __construct(
        int $package_id,
        string $code,
        string $receiver_phone,
        string $cod_usd,
        string $arrive_date,
        string $arrive_time,
        string $zone_name,
        int $status_id,
        string $status_code,
        ?string $cod_khr = "0",
        ?string $receiver_address = null,
        ?string $image = null,
        ?string $driver_name = null,
        ?string $driver_phone = null,
        public readonly string $finished_date,
        public readonly string $finished_time,
        public readonly ?string $reason,
        ?string $remarks = null,
        ?string $fees,
        ?string $taxi_fee,
        public array $driver_contacts = []
    ) {
        parent::__construct(
            package_id:$package_id,
            code: $code,
            receiver_phone: $receiver_phone,
            cod_usd: $cod_usd,
            arrive_date: $arrive_date,
            arrive_time: $arrive_time,
            zone_name: $zone_name,
            status_id: $status_id,
            status_code: $status_code,
            cod_khr: $cod_khr,
            receiver_address: $receiver_address,
            image: $image,
            driver_name: $driver_name,
            driver_phone: $driver_phone,
            fees: $fees,
            taxi_fee: $taxi_fee,
            remarks: $remarks
        );
    }
}
