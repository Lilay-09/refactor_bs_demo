<?php
namespace App\DTO;

use App\Models\PaymentTransaction;
class MerchantSettledTransactionByIdDTO {
    public function __construct(
        public readonly int $id,
        public readonly string $payment_date,
        public readonly string $payment_time,
        public readonly string $merchant_name,
        public readonly string $merchant_code,
        public readonly string $to_account,
        public readonly string $amount,
        public readonly ?string $tran_via,
        public readonly ?string $payment_ref,
        public readonly string $currency,
        public readonly string $performed_by,
        public readonly object $details
    ) {}

    public static function fromModel(PaymentTransaction $pmtTrx): self
    {
        return new static(
            id: $pmtTrx->id,
            payment_time: $pmtTrx->payment_time,
            payment_date: $pmtTrx->payment_date,
            merchant_name: $pmtTrx->merchant_name,
            merchant_code: $pmtTrx->merchant_code,
            amount: (string) $pmtTrx->amount,
            currency: $pmtTrx->currency,
            tran_via: $pmtTrx->tran_via,
            to_account: (string) $pmtTrx->to_account,
            performed_by: $pmtTrx->performed_by,
            payment_ref: $pmtTrx->payment_ref,
            details: $pmtTrx->disbursement,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
