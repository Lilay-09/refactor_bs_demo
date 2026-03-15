<?php

namespace App\Services;

interface PackageService
{
    public static function deductRowAmountBase($usd, $khr, float $amountUsd, float $exchangeRate = 4000): array;

    public static function calculateCodAmtBothCurrencies(
        float $amountUsd,
        float $amountKhr,
        string $userType,
        string $payer,
        int $pkgStatusId,
        float $fees,
        float $taxiFee,
        float $exchangeRate = 4000
    ): array;

    
}
