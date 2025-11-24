<?php

namespace App\Services;

use App\Enums\TrackingStatus;
use App\Models\Package;
use DataResponse;
use Google\Rpc\Help;
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
            'merchant:id,phone,username,code',
            'driver:id,phone,username,code',
            'createUser:id,username,code',
            'updateUser:id,username,code',
            'deletedUser:id,username,code',
            'returnUser:id,username,code',
        ])
        ->orderBy('id','desc');
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
            $statusId = $q->status_id;
            $q->status = TrackingStatus::tryFrom($q->status_id)->label();
            $finishDate = Helper::formatDateTime($q->updated_at);
            if($statusId == TrackingStatus::DELIVERED->value){
                $finishDate = Helper::formatCustomDateTime($q->delivered_datetime);
            }elseif($statusId == TrackingStatus::FAILED->value){
                $finishDate = Helper::formatCustomDateTime($q->failed_datetime);
            }elseif($statusId == TrackingStatus::FAILED_WITH_FEE->value){
                $finishDate = Helper::formatCustomDateTime($q->failed_datetime);
            }elseif($statusId == TrackingStatus::ON_DELIVERY->value){
                $finishDate = Helper::formatCustomDateTime($q->assign_driver_datetime);
            }elseif($statusId == TrackingStatus::AT_WAREHOUSE->value){
                $finishDate = Helper::formatCustomDateTime($q->arrive_warehouse_datetime);
            }elseif($statusId == TrackingStatus::RETURNED->value){
                $finishDate = Helper::formatCustomDateTime($q->returned_datetime);
            }
            $q->warehouse_date = Helper::formatCustomDateTime($q->arrive_warehouse_datetime);
            $q->returning_date = Helper::formatCustomDateTime($q->assigned_return_at);
            $q->finish_date = $finishDate;
            $q->deleted_date = Helper::formatDateTime($q->deleted_at);
            unset(
                $q->status_id, $q->create_uid, $q->update_uid, $q->deleted_uid, $q->returned_uid,
                $q->driver_id,$q->merchant_id,$q->created_at,$q->updated_at,$q->deleted_at,
                $q->returned_datetime,$q->arrive_warehouse_datetime, $q->assign_driver_datetime,
                $q->failed_datetime, $q->delivered_datetime
            );
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
