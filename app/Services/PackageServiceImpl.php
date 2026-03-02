<?php

namespace App\Services;

use Helper;

class PackageServiceImpl
{
    // Your service methods go here
    public static function deductRowAmountBase($usd, $khr, float $amountUsd, float $exchangeRate = 4000): array {
        // Step 1: Deduct from USD first
        if ($usd >= $amountUsd) {
            $usd -= $amountUsd;
            return [
                'amount_usd' => $usd,
                'amount_khr' => $khr
            ];
        }

        // Step 2: Not enough USD, use all available USD
        $remainingUsd = $amountUsd - $usd;
        $usd = 0;

        // Step 3: Try deduct from KHR equivalent
        $deductKhr = $remainingUsd * $exchangeRate;

        if ($khr >= $deductKhr) {
            $khr -= $deductKhr;
        } else {
            // Not enough KHR, consume all KHR and push USD negative
            $remainingKhr = $deductKhr - $khr;
            $khr = 0;
            $usd -= $remainingKhr / $exchangeRate; // USD goes negative
        }
        return [
            'amount_usd' => $usd,
            'amount_khr' => $khr
        ];
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
        if ($pkgStatusId === 9) {
            $fees += $taxiFee;
        }
        if($pkgStatusId == 19 && ($amountKhr > 0 || $amountUsd > 0)){
            $fees = 0;
        }

        // if($payer == 'receiver' && $userType == 'merchant' &&$pkgStatusId == 19){
        //     $amountUsd = 0;
        //     $amountKhr = 0;
        //     $fees = 0;
        // }
        // Deduct final fees
        Helper::deductAmountBase($amountUsd, $amountKhr, $fees, $exchangeRate);
        // Log::info($amountUsd.' --- '.$amountKhr);
        return [
            'amount_usd' => number_format($amountUsd, 2, '.', ''),
            'amount_khr' => number_format($amountKhr, 0, '.', ''),
            'all_fees'   => number_format($fees, 2, '.', ''),
        ];
    }

}
