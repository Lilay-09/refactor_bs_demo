<?php

namespace App\DTO\Mobile;


class DisbursementDTO
{
    public function __construct(
        public readonly string $time,
        public readonly string $type,
        public readonly string $amount,
        public readonly TransactionDTO $details,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            time: $data['time'],
            type: $data['type'],
            amount: (string) $data['amount'],
            details: $data['details']
        );
    }

    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'amount' => $this->amount,
            'details' => $this->details->toArray(),
        ];
    }
}
