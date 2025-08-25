<?php
namespace App\DTO;

use App\Models\Disbursement;
class MerchantRequestedSettlementDTO {
    public function __construct(
        public readonly int $id,
        public readonly string $requested_date,
        public readonly string $requested_time,
        public readonly string $merchant_name,
        public readonly string $merchant_code,
        public readonly string $package_count,
        public readonly string $driver_cod_usd,
        public readonly string $driver_cod_khr,
        public readonly string $cod_usd,
        public readonly string $cod_khr,
        public readonly string $fees,
        public readonly string $taxi,
        public readonly string $cod_to_be_paid_usd,
        public readonly string $cod_to_be_paid_khr,
        public readonly string $status,
        public readonly ?string $requested_username = null,
        public readonly ?array $bank_accounts = [],
    ) {}

    public static function fromModel(Disbursement $dis): self
    {
        return new static(
            id: $dis->id,
            requested_date: $dis->requested_date ?? '',
            requested_time: $dis->requested_time ?? '',
            merchant_name: $dis->merchant_name ?? '',
            merchant_code: $dis->merchant_code ?? '',
            package_count: (string) ($dis->package_count),
            driver_cod_usd: (string) $dis->driver_cod_usd,
            driver_cod_khr: (string) $dis->driver_cod_khr,
            cod_usd: (string) $dis->cod_usd,
            cod_khr: (string) $dis->cod_khr,
            fees: (string) $dis->fees,
            taxi: (string) $dis->taxi_fee,
            cod_to_be_paid_usd: $dis->cod_to_be_paid_usd,
            cod_to_be_paid_khr: $dis->cod_to_be_paid_khr,
            requested_username: $dis->requested_username,
            status: $dis->status,
            bank_accounts: is_array($dis->bank_accounts)
                ? $dis->bank_accounts
                : (!empty($dis->bank_accounts) ? json_decode($dis->bank_accounts, true) : []),
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
