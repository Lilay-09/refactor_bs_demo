<?php
namespace App\DTO;

use App\Models\Disbursement;
class MerchantRequestedSettlementDTO {
    public function __construct(
        public readonly int $id,
        public readonly string $requested_date,
        public readonly string $merchant_name,
        public readonly string $package_count,
        public readonly string $cod,
        public readonly string $fees,
        public readonly string $taxi,
        public readonly string $cod_collected,
        public readonly string $cod_to_be_paid_usd,
        public readonly string $cod_to_be_paid_khr,
        /** @var string[]|null */
        public readonly ?array $bank_accounts = null,
    ) {}

    public static function fromModel(Disbursement $dis): self
    {
        return new static(
            id: $dis->id,
            requested_date: $dis->requested_date ?? '',
            merchant_name: $dis->merchant?->name ?? '',
            package_count: (string) ($dis->package_count),
            cod: (string) $dis->cod,
            fees: (string) $dis->fees,
            taxi: (string) $dis->taxi_fee,
            cod_collected: (string) $dis->cod_collected,
            cod_to_be_paid_usd: $dis->cod_to_be_paid_usd,
            cod_to_be_paid_khr: $dis->cod_to_be_paid_khr,
            bank_accounts: $dis->bank_accounts ?? null
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
