<?php


class HomePaymentDTO{
    public function __construct(
        public readonly string $unpaidAmt,
        public readonly string $acceptedOrderCount,
        public readonly string $deliveredPackageCount,
        public readonly ?string $salary = null,
    ) {}

    public static function fromModel(object $data): self
    {
        return new self(
            unpaidAmt: $data->unpaid_amount,
            acceptedOrderCount: ''  ,
            deliveredPackageCount: '',
            salary:''
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
