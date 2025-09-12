<?php

namespace App\DTO\Mobile;
class HomeBalanceCardDTO{
    public function __construct(
        public readonly string $settleAmountUsd,
        public readonly string $settleAmountKhr,
        public readonly string $earning,
        public readonly string $pickupCount,
        public readonly string $deliveryCount,
        public readonly string $delivered_pkg_count,
        public readonly string $accepted_order_count

    ) {}

    public static function fromModel(array $data): self
    {
        return new self(
            settleAmountUsd:$data['settleAmountUsd'],
            settleAmountKhr: $data['settleAmountKhr'],
            earning: $data['earning'],
            pickupCount: $data['pickupCount'],
            deliveryCount: $data['deliveryCount'],
            accepted_order_count:$data['accepted_order_count'],
            delivered_pkg_count:$data['delivered_pkg_count']
        );
    }


    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
