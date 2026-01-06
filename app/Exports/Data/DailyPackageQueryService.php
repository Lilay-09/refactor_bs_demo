<?php

namespace App\Exports\Data;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;

class DailyPackageQueryService {
    public function getQuery(array $filters = []): Builder
    {
        $q = Package::query()
            ->where('is_deleted', 0)
            ->where('outstanding', 0)
            ->with([
                'status',
                'driver:id,code,username,phone',
                'merchant:id,code,username,phone',
                'returnUser:id,code,username,phone',
                'pickupDriver:id,code,username,phone',
                'merchant.merchantPriceList',
                'merchant.merchantPriceList.priceList.priceListName',
                'branchLocation:id,name_en'
            ])
            ->selectRaw('
                zone_name,zone_code,qr_code,merchant_id,driver_id,returned_uid,payer,price_khr,
                driver_cod_usd,driver_cod_khr,receiver_address,remarks,receiver_phone,cod,price,delivery_fee,
                additional_fee,driver_total,merchant_total,status_id,remarks,arrive_warehouse_datetime,
                assign_driver_datetime,updated_at,failed_datetime,returned_datetime,delivered_datetime,
                other_fee,created_at,product_type,taxi_fee,pickup_uid,branch_id
            ');

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $q->where(function($q2) use ($search) {
                $q2->where('qr_code','LIKE',"%{$search}%")
                    ->orWhere('receiver_phone','LIKE',"%{$search}%")
                    ->orWhere('zone_code','LIKE',"%{$search}%");
            });
        }

        if (!empty($filters['driver_id'])) {
            $driverId = $filters['driver_id'];
            $q->where(function($q2) use ($driverId){
                $q2->where(function ($q3) use ($driverId){
                    $q3->whereIn('status_id', [23,11])
                        ->where('returned_uid', $driverId);
                })->orWhere(function($q3) use ($driverId){
                    $q3->whereNotIn('status_id', [23,11])
                        ->where('driver_id', $driverId);
                });
            });
        }

        // Add other filters (status, merchant, branch, warehouse, pickup_driver, price_list, zone_code)
        // Example:
        if (!empty($filters['status'])) {
            $q->whereIn('status_id', explode(',', $filters['status']));
        }
        if (!empty($filters['merchant_id'])) {
            $q->where('merchant_id', $filters['merchant_id']);
        }

        // Date filters
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $start = $filters['startDate'].' 00:00:00';
            $end = $filters['endDate'].' 23:59:59';
            $q->where(function($q2) use ($start, $end){
                $q2->whereBetween('failed_datetime', [$start,$end])->whereIn('status_id',[10,19])
                   ->orWhereBetween('delivered_datetime', [$start,$end])->where('status_id',9)
                   ->orWhereBetween('returned_datetime', [$start,$end])->where('status_id',23);
            });
        } elseif (!empty($filters['arrive_start_date'])) {
            $start = $filters['arrive_start_date'].' 00:00:00';
            $end = ($filters['arrive_end_date'] ?? $filters['arrive_start_date']).' 23:59:59';
            $q->whereBetween('arrive_warehouse_datetime', [$start, $end]);
        }

        return $q->orderByDesc('created_at');
    }
}