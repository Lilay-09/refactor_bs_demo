<?php

namespace App\DTO\Mobile;

use App\Models\PaymentTransaction;

class TransactionDTO
{
    public function __construct(
        public readonly string $amount,
        public readonly ?string $tran_id,
        public readonly ?string $payment_method,
        public readonly ?string $from_account,
        public readonly ?string $to_account,
        public readonly ?string $cashier,
        public readonly ?string $remarks,
        public readonly ?string $transaction_date,
    ) {}

    public static function fromModel(PaymentTransaction $data,?string $cashierName = null): self
    {
        return new self(
            amount: (string) ($data['amount'] ?? '0'),
            tran_id: $data['tran_id'] ?? null,
            payment_method: $data['payment_method'] ?? null,
            from_account: $data['from_account'] ?? null,
            to_account: $data['to_account'] ?? null,
            cashier: $data['cashier'] ?? $cashierName,
            remarks: $data['remarks'] ?? null,
            transaction_date: $data['transaction_date'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'tran_id' => $this->tran_id,
            'payment_method' => $this->payment_method,
            'from_account' => $this->from_account,
            'to_account' => $this->to_account,
            'cashier' => $this->cashier,
            'remarks' => $this->remarks,
            'transaction_date' => $this->transaction_date,
        ];
    }
}
