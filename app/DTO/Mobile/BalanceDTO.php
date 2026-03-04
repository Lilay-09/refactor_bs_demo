<?php
namespace App\DTO\Mobile;

class BalanceDTO
{
    public function __construct(
        public readonly ?string $amount_usd = 'USD 0',
        public readonly ?string $amount_khr = 'KHR 0',
    ){}
}