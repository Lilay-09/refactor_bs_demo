<?php

use App\Models\Package;

abstract class PackageDTO{
    public function __construct(
        public readonly int $id,
        public readonly string $merchant_name,
        public readonly string $receiver_phone,
        public readonly string $status_id,
        public readonly ?string $receiver_address = null,
        public readonly ?string $status = null,

    ) {}

    public static function fromModel(Package $package): self
    {
        return new static(
            id: $package->id,
            merchant_name: $package->merchant?->name ?? '',
            receiver_phone: $package->receiver_phone,
            status_id: $package->status_id,
            receiver_address: $package->receiver_address ?? '',
            status: $package->status?->name_en ?? null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
