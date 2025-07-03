<?php

namespace App\DTO\Mobile;
class HomePaymentDTO{
    public function __construct(
        public readonly string $unpaid_amt,
        public readonly string $accepted_order_count,
        public readonly string $delivered_pkg_count,
        public readonly ?string $salary = null,
        public readonly ?string $on_delivery_count = null,
        public readonly ?string $pickup_count = null,
    ) {}

    public static function fromModel(object $data): self
    {
        return new self(
            unpaid_amt: $data->unpaid_amt,
            accepted_order_count: $data->accepted_order_count,
            delivered_pkg_count: $data->delivered_pkg_count,
            salary: $data->salary,
            on_delivery_count: $data->on_delivery_count,
            pickup_count: $data->pickup_count,

        );
    }


    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
