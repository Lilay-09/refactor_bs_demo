<?php
namespace App\DTO\Mobile\V2;

class MerchantTrackingActivityDTO
{
    public function __construct(
        public readonly string $total_package,
        public readonly string $total_cod,
        public readonly string $total_cod_khr,
        public readonly ?int $pending = 0,
        public readonly ?int $pickup = 0,
        public readonly ?int $at_warehouse = 0,
        public readonly ?int $on_delivery = 0,
        public readonly ?int $delivered = 0,
        public readonly ?int $failed = 0,
        public readonly ?int $returned = 0,
        public readonly ?int $failed_with_fee = 0,
    ) {}
}