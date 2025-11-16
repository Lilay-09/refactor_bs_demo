<?php

namespace App\Services;

use App\Enums\TrackingStatus;
use App\Models\Package;
use DataResponse;
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

    public static function getPackageInformations(array $filter)
    {
        //
        $statusId = $filter['status_id'] ?? null;
        $merchantPhone = $filter['merchant_phone'] ?? null;
        $driverPhone = $filter['driver_phone'] ?? null;
        $receiverPhone = $filter['receiver_phone'] ?? null;
        $query = Package::query()
        ->with([
            'merchant:id,phone,usename,code',
            'driver:id,phone,username,code',
            'createUser:id,username,code',
            'updateUser:id,username,code',
            'deletedUser:id,username,code',
            'returnUser:id,username,code',
        ]);
        if($statusId){
            $query->where('status_id',$statusId);
        }

        if($merchantPhone){
            $query->whereHas('merchant',function($q) use($merchantPhone){
                $q->where('phone',$merchantPhone);
            });
        }

        if($driverPhone){
            $query->whereHas('driver',function($q) use($driverPhone){
                $q->where('phone',$driverPhone);
            });
        }
        if($receiverPhone){
            $query->where('receiver_phone',$receiverPhone);
        }
        $callback = function($q){
            $q->status = TrackingStatus::tryFrom($q->status_id)->label();
            unset($q->status_id, $q->create_uid, $q->update_uid, $q->deleted_uid, $q->returned_uid,$q->driver_id,$q->merchant_id);
            return $q;
        };
        return DataResponse::PaginationV1(
            query:$query,
            filter:$filter,
            limit:100,
            transformCallback:$callback
        );
    }
}
