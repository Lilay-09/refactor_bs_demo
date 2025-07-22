<?php

namespace App\DTO\Mobile;

use App\Models\Package;
class DeliveryTripsPackagesDTO{
    public function __construct(
        public readonly int $id,
        public readonly ?string $qr_code,
        public readonly string $merchant_name,
        public readonly string $merchant_phone,
        public readonly int $status_id,
        public readonly float $total,
        public readonly ?float $total_khr,
        public readonly ?string $zone_name,
        public readonly ?string $zone_code,
        public readonly ?string $status,
        public readonly ?string $date,
        public readonly ?string $time,
        public readonly ?string $receiver_phone,
        public readonly ?string $receiver_address,
        public readonly ?string $self_notes,
        public readonly ?bool $is_contact,
        public readonly ?float $exchange_rate
    ) {}
    public static function fromModel(Package $data): self
    {
        return new self(
            id: $data->id,
            qr_code: $data->qr_code,
            status_id: $data->status_id,
            merchant_name: $data->merchant_name,
            total: $data->total,
            merchant_phone: $data->merchant_phone,
            zone_name: $data->salary,
            zone_code: $data->zone_code,
            status: $data->status ?? null,
            date: $data->date ?? null,
            time: $data->time ?? null,
            receiver_phone: $data->receiver_phone ?? null,
            receiver_address: $data->receiver_address ?? null,
            self_notes: $data->self_notes ?? null,
            is_contact: $data->is_contact ?? null,
            exchange_rate: $data->exchange_rate,
            total_khr: $data->total_khr ?? 0
        );
    }


    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
