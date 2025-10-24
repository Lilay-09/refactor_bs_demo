<?php

namespace App\Services;

use Helper;

class PackageTrailServiceImpl
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public static function calculateCodAmtBothCurrencies(
        float $amountUsd,
        float $amountKhr,
        string $userType,
        string $payer,
        int $pkgStatusId,
        float $fees,
        float $taxiFee,
        float $exchangeRate = 4000
    ): array {
        // Define valid payer types per user
        $validPayers = [
            'driver' => 'receiver',
            'merchant' => 'sender',
        ];

        // Reset fees if payer is not valid for the user type
        if (isset($validPayers[$userType]) && $payer !== $validPayers[$userType]) {
            $fees = 0;
        }

        // Add taxi fee if package is delivered
        if ($pkgStatusId === 9 && $userType == 'driver') {
            $fees += $taxiFee;
        }else{
            $fees += $taxiFee;
        }

        // Deduct final fees
        Helper::deductAmountBase($amountUsd, $amountKhr, $fees, $exchangeRate);
        return [
            'amount_usd' => $amountUsd,
            'amount_khr' => $amountKhr,
            'all_fees'   => $fees,
        ];
    }
}
