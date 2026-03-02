<?php

namespace App\DTO\Mobile;

class DriverTransaction{
    public function __construct(
        public readonly array $toBeSettleUsd,
        public readonly array $toBeSettleKhr,
        public readonly array $transactions,

    ) {}

    public static function fromModel(array $data): self
    {
        return new self(
            toBeSettleUsd:$data['settleAmountUsd'],
            toBeSettleKhr: $data['settleAmountKhr'],
            transactions: $data['transactions']
        );
    }


    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
