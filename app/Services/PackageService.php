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

    /**
     * Batch assign selected packages to a driver
     * 
     * @param array $data ['package_ids', 'driver_id', 'notes']
     * @return object DataResponse
     */
    public function batchAssignPackagesToDriver(array $data): object;

    /**
     * Batch assign packages by QR codes (for scanner)
     * 
     * @param array $data ['user', 'qr_codes', 'driver_id', 'notes']
     * @return object DataResponse
     */
    public function batchAssignPackagesByQrCode(array $data): object;
}
