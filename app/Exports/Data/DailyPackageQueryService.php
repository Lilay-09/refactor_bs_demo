<?php

namespace App\Exports\Data;

use App\Exceptions\BadRequestExcept;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DailyPackageQueryService {
    // public function getQuery(array $filters = []): Builder
    // {
    //     if (isset($filters['startDate']) && isset($filters['endDate'])) {
    //         $startDate = Carbon::parse($filters['startDate'])->startOfDay(); // 00:00:00
    //         $endDate = Carbon::parse($filters['endDate'])->endOfDay();       // 23:59:59

    //         // Check if startDate is more than 2 months before endDate
    //         if ($startDate->diffInMonths($endDate) > 2) {
    //             throw new BadRequestExcept('Start date cannot be more than 2 months before the end date.');
    //         }
    //     }
        // $q = Package::query()
        //     ->where('is_deleted', 0)
        //     ->where('outstanding', 0)
        //     ->with([
        //         'status',
        //         'driver:id,code,username,phone',
        //         'merchant:id,code,username,phone',
        //         'returnUser:id,code,username,phone',
        //         'pickupDriver:id,code,username,phone',
        //         'merchant.merchantPriceList',
        //         'merchant.merchantPriceList.priceList.priceListName',
        //         'branchLocation:id,name_en'
        //     ])
        //     ->selectRaw('
        //         zone_name,zone_code,qr_code,merchant_id,driver_id,returned_uid,payer,price_khr,
        //         driver_cod_usd,driver_cod_khr,receiver_address,remarks,receiver_phone,cod,price,delivery_fee,
        //         additional_fee,driver_total,merchant_total,status_id,remarks,arrive_warehouse_datetime,
        //         assign_driver_datetime,updated_at,failed_datetime,returned_datetime,delivered_datetime,
        //         other_fee,created_at,product_type,taxi_fee,pickup_uid,branch_id
        //     ');

    //     // Apply filters
    //     if (!empty($filters['search'])) {
    //         $search = $filters['search'];
    //         $q->where(function($q2) use ($search) {
    //             $q2->where('qr_code','LIKE',"%{$search}%")
    //                 ->orWhere('receiver_phone','LIKE',"%{$search}%")
    //                 ->orWhere('zone_code','LIKE',"%{$search}%");
    //         });
    //     }

    //     if (!empty($filters['driver_id'])) {
    //         $driverId = $filters['driver_id'];
    //         $q->where(function($q2) use ($driverId){
    //             $q2->where(function ($q3) use ($driverId){
    //                 $q3->whereIn('status_id', [23,11])
    //                     ->where('returned_uid', $driverId);
    //             })->orWhere(function($q3) use ($driverId){
    //                 $q3->whereNotIn('status_id', [23,11])
    //                     ->where('driver_id', $driverId);
    //             });
    //         });
    //     }

    //     // Add other filters (status, merchant, branch, warehouse, pickup_driver, price_list, zone_code)
    //     // Example:
    //     if (!empty($filters['status'])) {
    //         $q->whereIn('status_id', explode(',', $filters['status']));
    //     }
    //     if (!empty($filters['merchant_id'])) {
    //         $q->where('merchant_id', $filters['merchant_id']);
    //     }

    //     // Date filters
    //     if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
    //         $start = $filters['startDate'].' 00:00:00';
    //         $end = $filters['endDate'].' 23:59:59';
    //         $q->where(function($q2) use ($start, $end){
    //             $q2->whereBetween('failed_datetime', [$start,$end])->whereIn('status_id',[10,19])
    //                ->orWhereBetween('delivered_datetime', [$start,$end])->where('status_id',9)
    //                ->orWhereBetween('returned_datetime', [$start,$end])->where('status_id',23);
    //         });
    //     } elseif (!empty($filters['arrive_start_date'])) {
    //         $start = $filters['arrive_start_date'].' 00:00:00';
    //         $end = ($filters['arrive_end_date'] ?? $filters['arrive_start_date']).' 23:59:59';
    //         $q->whereBetween('arrive_warehouse_datetime', [$start, $end]);
    //     }

    //     return $q->orderByDesc('created_at');
    // }
    public function getQuery(array $filters = []): Builder
    {
        // -------------------------------
        // Date validation (KEEP THIS)
        // -------------------------------
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $startDate = Carbon::parse($filters['startDate'])->startOfDay();
            $endDate   = Carbon::parse($filters['endDate'])->endOfDay();

            if ($startDate->diffInMonths($endDate) > 2) {
                throw new BadRequestExcept(
                    'Start date cannot be more than 2 months before the end date.'
                );
            }
        }

        // -------------------------------
        // BASE QUERY (FLAT, NO RELATIONS)
        // -------------------------------
        $q = DB::table('packages')
            ->where('packages.is_deleted', 0)
            ->where('packages.outstanding', 0)

            // USERS
            ->leftJoin('users as drivers', 'drivers.id', '=', 'packages.driver_id')
            ->leftJoin('users as merchants', 'merchants.id', '=', 'packages.merchant_id')
            ->leftJoin('users as return_users', 'return_users.id', '=', 'packages.returned_uid')
            ->leftJoin('users as pickup_drivers', 'pickup_drivers.id', '=', 'packages.pickup_uid')

            // OTHERS
            ->leftJoin('branches', 'branches.id', '=', 'packages.branch_id')
            ->leftJoin('tracking_statuses', 'tracking_statuses.id', '=', 'packages.status_id')
            ->leftJoin('merchant_price_list as mpl', function($join) {
                $join->on('mpl.merchant_id', '=', 'packages.merchant_id');
                    // ->where('mpl.is_deleted',false); // optional soft delete
            })
            
            // Join price_list
            ->leftJoin('price_list as pl', 'pl.id', '=', 'mpl.price_list_id')
            ->leftJoin('price_list_names as pln', 'pln.id', '=', 'pl.price_list_name_id')

            ->select([
                'packages.id',

                'packages.zone_name',
                'packages.zone_code',
                'packages.qr_code',
                'packages.status_id',

                'packages.receiver_phone',
                'packages.receiver_address',
                'packages.driver_cod_usd',
                'packages.driver_cod_khr',
                'packages.taxi_fee',
                'packages.other_fee',
                'pln.name as price_list_name',
                'packages.cod',
                'packages.price',
                'packages.price_khr',
                'packages.delivery_fee',
                'packages.additional_fee',
                'packages.driver_total',
                'packages.merchant_total',

                'packages.failed_datetime',
                'packages.delivered_datetime',
                'packages.returned_datetime',
                'packages.arrive_warehouse_datetime',

                'drivers.code as driver_code',
                'drivers.username as driver_name',
                'drivers.phone as driver_phone',

                'pickup_drivers.code as pickup_driver_code',
                'pickup_drivers.username as pickup_driver_name',

                'return_users.code as return_user_code',
                'return_users.username as return_user_name',

                'merchants.code as merchant_code',
                'merchants.username as merchant_name',
                'merchants.phone as merchant_phone',

                'branches.name_en as branch_name',

                'tracking_statuses.name as status_name',

                'packages.created_at',
                'packages.updated_at',
            ]);

        // -------------------------------
        // FILTERS
        // -------------------------------

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $q->where(function ($s) use ($search) {
                $s->where('packages.qr_code', 'LIKE', "%{$search}%")
                  ->orWhere('packages.receiver_phone', 'LIKE', "%{$search}%")
                  ->orWhere('packages.zone_code', 'LIKE', "%{$search}%");
            });
        }

        // Driver logic
        if (!empty($filters['driver_id'])) {
            $driverId = $filters['driver_id'];

            $q->where(function ($q2) use ($driverId) {
                $q2->where(function ($x) use ($driverId) {
                        $x->whereIn('packages.status_id', [23, 11])
                          ->where('packages.returned_uid', $driverId);
                    })
                    ->orWhere(function ($x) use ($driverId) {
                        $x->whereNotIn('packages.status_id', [23, 11])
                          ->where('packages.driver_id', $driverId);
                    });
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $q->whereIn('packages.status_id', explode(',', $filters['status']));
        }

        // Merchant filter
        if (!empty($filters['merchant_id'])) {
            $q->where('packages.merchant_id', $filters['merchant_id']);
        }

        // Branch filter
        if (!empty($filters['branch_id'])) {
            $q->where('packages.branch_id', $filters['branch_id']);
        }

        // -------------------------------
        // DATE FILTERS (INDEX FRIENDLY)
        // -------------------------------
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $start = $filters['startDate'] . ' 00:00:00';
            $end   = $filters['endDate'] . ' 23:59:59';

            $q->where(function ($w) use ($start, $end) {
                $w->where(function ($x) use ($start, $end) {
                        $x->whereIn('packages.status_id', [10, 19])
                          ->whereBetween('packages.failed_datetime', [$start, $end]);
                    })
                  ->orWhere(function ($x) use ($start, $end) {
                        $x->where('packages.status_id', 9)
                          ->whereBetween('packages.delivered_datetime', [$start, $end]);
                    })
                  ->orWhere(function ($x) use ($start, $end) {
                        $x->where('packages.status_id', 23)
                          ->whereBetween('packages.returned_datetime', [$start, $end]);
                    });
            });
        }

        // Arrive warehouse filter
        if (!empty($filters['arrive_start_date'])) {
            $start = $filters['arrive_start_date'] . ' 00:00:00';
            $end   = ($filters['arrive_end_date'] ?? $filters['arrive_start_date']) . ' 23:59:59';

            $q->whereBetween('packages.arrive_warehouse_datetime', [$start, $end]);
        }

        // -------------------------------
        // CRITICAL FOR CHUNKING
        // -------------------------------
        return $q->orderBy('packages.id');
    }
}