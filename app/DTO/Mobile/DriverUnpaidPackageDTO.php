<?php

namespace App\DTO\Mobile;

use App\Models\Package;
class DriverUnpaidPackageDTO{
    public function __construct(
        public readonly int $package_id,
        public readonly ?string $qr_code,
        public readonly string $merchant_name,
        public readonly string $merchant_phone,
        public readonly int $status_id,
        public readonly ?string $status_code,
        public readonly string $merchant_total,
        public readonly string $driver_total,
        public readonly ?string $extra_charge,
        public readonly ?string $receiver_address,
        public readonly ?string $receiver_name,
        public readonly ?string $receiver_phone,
        public readonly ?string $fees,
        public readonly ?string $delivery_remarks,
        public readonly ?string $remarks,
        public readonly ?string $arrive_date,
        public readonly ?string $arrive_time,
        public readonly ?string $product_type,
        public readonly ?string $payer,
        // public readonly ?string $driver_id,
        // public readonly ?string $returned_uid,
        // public readonly ?string $cod,
        public readonly string $date,
        public readonly string $time,
        public readonly string $price_khr,
        public readonly string $price_usd,
        public readonly string $taxi_fee,
        public readonly string $cod_usd,
        public readonly string $cod_khr,
        public readonly ?array $images
    ) {}


    public static function fromModel(Package $data): self
    {
        return new self(
            package_id: (int) $data->package_id,
            qr_code: $data->qr_code ?? null,
            merchant_name: $data->merchant_name ?? '',
            merchant_phone: $data->merchant_phone ?? '',
            status_id: (string) $data->status_id,
            status_code: $data->status_code ?? null,
            remarks: $data->remarks,
            merchant_total: (string) $data->merchant_total,
            driver_total: (string) $data->driver_total,
            extra_charge: isset($data->extra_charge) ? (string) $data->extra_charge : null,
            receiver_address: $data->receiver_address ?? null,
            receiver_name: $data->receiver_name ?? null,
            receiver_phone: $data->receiver_phone ?? null,
            fees: $data->fees,
            delivery_remarks: $data->delivery_remarks,
            arrive_date: $data->arrive_date ?? null,
            arrive_time: $data->arrive_time ?? null,
            product_type: $data->product_type ?? null,
            payer: $data->payer ?? null,
            price_usd:$data->price_usd,
            price_khr: $data->price_khr,
            // driver_id: isset($data->driver_id) ? (string) $data->driver_id : null,
            // returned_uid: isset($data->returned_uid) ? (string) $data->returned_uid : null,
            // cod: isset($data->cod) ? ($data->cod ? 'true' : 'false') : null,
            date: (string) $data->date,
            time: (string) $data->time,
            taxi_fee: $data->taxi_fee,
            cod_usd: $data->driver_cod_usd,
            cod_khr: $data->driver_cod_khr,
            images: $data->images
        );
    }



    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
