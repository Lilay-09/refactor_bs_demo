<?php

namespace App\DTO\Mobile;
class MerchantBalanceDTO
{
    public function __construct(
        /** @var BalanceDTO|null */
        public readonly ?BalanceDTO $availableCOD = null,
        /** @var BalanceDTO|null */
        public readonly ?BalanceDTO $cashEarned = null,
    ){}
}