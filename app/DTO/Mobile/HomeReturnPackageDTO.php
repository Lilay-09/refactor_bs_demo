<?php
namespace App\DTO\Mobile;
use App\Models\Package;
class HomeReturnPackageDTO{
    public function __construct(
        public readonly int $id,
        public readonly string $merchant_name,
        public readonly string $merchant_phone,
        public readonly ?string $status,
        public readonly ?string $map_url,
        public readonly ?string $remarks,
        public readonly ?string $date,
        public readonly ?string $time,
        public readonly ?string $map_address,
        public readonly object|array $telegram_link,
        public readonly string $qr_code,
        public readonly ?string $loc_lng,
        public readonly ?string $loc_lat,
        public readonly ?string $other_fee,
    ) {}

    public static function fromModel(Package $pkg): self
    {
        return new self(
            id: $pkg->id,
            merchant_name: $pkg->merchant_name,
            merchant_phone: $pkg->merchant_phone,
            status: $pkg->status ?? null,
            map_url: $pkg->map_url ?? null,
            remarks: $pkg->remarks ?? null,
            date: $pkg->date,
            time: $pkg->time,
            map_address: $pkg->map_address,
            telegram_link: $pkg->telegram_link ?? null,
            qr_code: $pkg->qr_code ?? '',
            loc_lng: $pkg->loc_lng,
            loc_lat: $pkg->loc_lat,
            other_fee: $pkg->other_fee
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
