<?php

namespace App\Exports\Data;
use App\Models\Package;
use App\Services\PackageTrailServiceImpl;
use Helper;
use Illuminate\Support\Facades\Log;

class DailyPackageFormatter
{
    protected string $lang;

    public function __construct(string $lang = 'en')
    {
        $this->lang = $lang;
    }

    public function format(Package $p): array
    {
        // Corrected fee calculation
        // $fees = ($p->delivery_fee ?? 0) + ($p->other_fee ?? 0);
        // $driverCodUsd = (float)($p->driver_cod_usd ?? 0);
        // $driverCodKhr = (float)($p->driver_cod_khr ?? 0);
        // $taxiFee = (float)($p->taxi_fee ?? 0);

        // Calculate merchant total (if needed for extra calculations)
        // $merchantTotal = PackageTrailServiceImpl::calculateCodAmtBothCurrencies(
        //     $driverCodUsd, $driverCodKhr, 'merchant', $p->payer, $p->status_id, $fees, $taxiFee
        // );

        $actionDate = match($p->status_id) {
            10, 19 => $p->failed_datetime,
            9 => $p->delivered_datetime,
            23 => $p->returned_datetime,
            default => null,
        };

        return [
            'no' => 0,
            'barcode' => $p->qr_code ?? '',
            'branch' => $p->branchLocation?->name_en ?? '',
            'merchant_name' => $p->merchant?->username ?? '',
            'receiver' => $p->receiver_address ?? '',
            'price_list' => $p->merchant?->merchantPriceList?->priceListName?->name ?? '',
            'driver' => ($p->status_id == 11 || $p->status_id == 23)
                ? $p->returnUser?->username ?? ''
                : $p->driver?->username ?? '',
            'pickup_driver' => $p->pickupDriver?->username ?? '',
            'transfer_driver' => '', // Optional
            'zone' => $p->zone_name ?? '',
            'zone_code' => $p->zone_code ?? '',
            'arrived_date' => $p->arrive_warehouse_datetime 
                ? Helper::formatCustomDateTime($p->arrive_warehouse_datetime,'d-M-Y h:i A')
                : null,
            'finished_date' => $actionDate 
                ? Helper::formatCustomDateTime($actionDate,'d-M-Y h:i A') 
                : null,

            // Separate Merchant COD columns
            'merchant_cod_usd' => Helper::getNumber($p->price ?? 0, 2),
            'merchant_cod_khr' => Helper::getNumber($p->price_khr ?? 0, 0),

            // Separate Driver COD columns
            'driver_cod_usd' => Helper::getNumber($p->driver_cod_usd ?? 0, 2),
            'driver_cod_khr' => Helper::getNumber($p->driver_cod_khr ?? 0, 0),

            'base_fee' => Helper::getNumber($p->delivery_fee ?? 0, 2),
            'taxi_fee' => Helper::getNumber($p->taxi_fee ?? 0, 2),
            'other_fee' => Helper::getNumber($p->other_fee ?? 0, 2),
            'status' => $this->lang == 'km'
                ? \App\Services\GeneralSettingService::$statusCodeTrans[$p->status_id] ?? ''
                : $p->status?->name ?? '',
            'remarks' => $p->remarks ?? '',
            'driver_remarks' => $p->delivery_remarks ?? '',
        ];
    }

    /**
     * Format a plain DB::table() row (flat stdClass or array)
     * for export with raw query.
     */
    public function rawFormat(object|array $p): array
    {
        // Convert object to array if needed
        if (is_object($p)) {
            $p = (array) $p;
        }

        // Determine action date (same logic)
        $actionDate = match($p['status_id'] ?? 0) {
            10, 19 => $p['failed_datetime'] ?? null,
            9 => $p['delivered_datetime'] ?? null,
            23 => $p['returned_datetime'] ?? null,
            default => null,
        };

        return [
            'no' => 0,
            'barcode' => $p['qr_code'] ?? '',
            'branch' => $p['branch_name'] ?? '',
            'merchant_name' => $p['merchant_name'] ?? '',
            'receiver' => $p['receiver_address'] ?? '',
            'price_list' => $p['price_list_name'] ?? '',
            'driver' => in_array($p['status_id'] ?? 0, [11, 23])
                ? $p['return_user_name'] ?? ''
                : $p['driver_name'] ?? '',
            'pickup_driver' => $p['pickup_driver_name'] ?? '',
            'transfer_driver' => '', // Optional
            'zone' => $p['zone_name'] ?? '',
            'zone_code' => $p['zone_code'] ?? '',
            'arrived_date' => isset($p['arrive_warehouse_datetime'])
                ? Helper::formatCustomDateTime($p['arrive_warehouse_datetime'], 'd-M-Y h:i A')
                : null,
            'finished_date' => $actionDate
                ? Helper::formatCustomDateTime($actionDate, 'd-M-Y h:i A')
                : null,

            // Separate Merchant COD columns
            'merchant_cod_usd' => Helper::getNumber($p['price'] ?? 0, 2),
            'merchant_cod_khr' => Helper::getNumber($p['price_khr'] ?? 0, 0),

            // Separate Driver COD columns
            'driver_cod_usd' => Helper::getNumber($p['driver_cod_usd'] ?? 0, 2),
            'driver_cod_khr' => Helper::getNumber($p['driver_cod_khr'] ?? 0, 0),

            'base_fee' => Helper::getNumber($p['delivery_fee'] ?? 0, 2),
            'taxi_fee' => Helper::getNumber($p['taxi_fee'] ?? 0, 2),
            'other_fee' => Helper::getNumber($p['other_fee'] ?? 0, 2),
            'status' => $this->lang == 'km'
                ? \App\Services\GeneralSettingService::$statusCodeTrans[$p['status_id']] ?? ''
                : $p['status_name'] ?? '',
            'remarks' => $p['remarks'] ?? '',
            'driver_remarks' => $p['delivery_remarks'] ?? '',
        ];
    }




}