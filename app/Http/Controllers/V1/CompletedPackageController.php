<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Models\PackageAttachment;
use App\Services\GeneralSettingService;
use App\Services\PickupCenterService;
use App\Services\TransactionService;
use App\Services\UserService;
use DB;
use Helper;
use Illuminate\Http\Request;

class CompletedPackageController extends Controller
{
    //
    public function getFinishedPackages(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $merchantId = $req->merchant_id;
        $driverId = $req->driver_id;
        $statusId = $req->status_id;
        $lang = $req->lang;
        $warehouseId = $req->warehouse_id;
        $paymentStatusId = $req->payment_status_id;
        $search = $req->search;
        // $driverSettled = ",
        //     CASE
        //         WHEN p.driver_payment_id IS NOT NULL AND dpmt.is_settled = true THEN 'Settled'
        //         WHEN p.driver_payment_id IS NOT NULL AND dpmt.is_settled = false THEN 'Approved'
        //         WHEN p.driver_disbursement_id IS NOT NULL AND dbur.is_settled = true THEN 'Settled'
        //         WHEN p.driver_disbursement_id IS NOT NULL AND dbur.is_settled = false THEN 'Approved'
        //         ELSE 'No Payment/Disbursement'
        //     END as driver_status
        // ";
        // $merchantSettled = ",CASE
        //     WHEN p.merchant_payment_id IS NOT NULL AND dpmt.is_settled = true THEN 'Settled'
        //     WHEN p.merchant_payment_id IS NOT NULL AND dpmt.is_settled = false THEN 'Approved'
        //     WHEN p.merchant_disbursement_id IS NOT NULL AND dbur.is_settled = true THEN 'Settled'
        //     WHEN p.merchant_disbursement_id IS NOT NULL AND dbur.is_settled = false THEN 'Approved'
        //     ELSE 'No Payment/Disbursement'
        // END as merchant_status";
        $qP = Package::query()->from('packages as p')
        ->where('p.company_id',$user->company_id)
        ->where('p.is_deleted',0)
        ->with('returnUser')
        ->join('users as d', function ($join) {
            $join->on('d.id', '=', DB::raw("CASE
                WHEN p.status_id = 11 THEN p.returned_uid
                ELSE p.driver_id
            END"));
        })

        // ->leftJoin('users as d','d.id','p.driver_id')
        // ->join('users as d', function ($join) use($statusId) {
        //     if ($statusId == 11) {
        //         // When status_id is 11, join on returned_uid
        //         $join->on('d.id', '=', 'p.returned_uid');
        //     } else {
        //         // Otherwise, join on driver_id
        //         $join->on('d.id', '=', 'p.driver_id');
        //     }
        // })

        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('orders as o','o.id','p.order_id')
        ->join('users as m','m.id','p.merchant_id')
        // ->leftJoin('payments as dpmt','dpmt.id','p.driver_payment_id') //** if driver paid or unpaid */
        // ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        // ->leftJoin('disbursements as dbur','dbur.id','p.driver_disbursement_id') //** if driver paid or unpaid */
        // ->leftJoin('disbursements as mbur','mbur.id','p.merchant_disbursement_id') //** if driver paid or unpaid */
        ->orderByRaw("
            GREATEST(
                COALESCE(p.delivered_datetime, '1970-01-01'),
                COALESCE(p.failed_datetime, '1970-01-01'),
                COALESCE(p.returned_datetime, '1970-01-01')
            ) DESC
        ")
        ->orderByDesc('p.id')

        ->whereIn('p.status_id',[9,11,19]) //* delivered and failed with fee
        ->where(function ($query) {
            $query->where('p.status_id', '!=', 19)    // wxclude status 19
                    ->orWhereNotNull('p.returned_uid'); // Include 19 only if returned_uid is not null
        });
        $select = ['p.driver_id','p.arrive_warehouse_datetime','p.returned_uid','p.receiver_address','p.delivered_datetime','m.username as merchant_name','m.phone as merchant_phone','d.username as driver_name','p.status_id','p.returned_datetime','p.id as package_id','d.id as driver_id','p.qr_code','p.price','ts.name as status_code','p.product_type','p.delivered_datetime','p.failed_datetime','p.taxi_fee','p.payer','p.cod','p.zone_code','p.zone_name','p.receiver_phone','p.delivery_type','p.delivery_fee','p.driver_total','p.merchant_total'];
        // ->selectRaw('p.arrive_warehouse_datetime,p.returned_uid,p.receiver_address,p.driver_disbursement_id,p.driver_payment_id,p.delivered_datetime,m.username as merchant_name,m.phone as merchant_phone,d.username as driver_name,p.status_id,p.returned_datetime,p.id as package_id,d.id as driver_id,p.qr_code,p.price,ts.name as status_code,p.product_type,p.delivered_datetime,p.failed_datetime,p.taxi_fee,p.payer,p.cod,p.zone_code,p.zone_name,p.receiver_phone,p.delivery_type,p.delivery_fee,p.driver_total,p.merchant_total'.$driverSettled.$merchantSettled);
        // ->select('p.driver_id','p.arrive_warehouse_datetime','p.returned_uid','p.receiver_address','p.driver_disbursement_id','p.driver_payment_id','p.delivered_datetime','m.username as merchant_name','m.phone as merchant_phone','d.username as driver_name','p.status_id','p.returned_datetime','p.id as package_id','d.id as driver_id','p.qr_code','p.price','ts.name as status_code','p.product_type','p.delivered_datetime','p.failed_datetime','p.taxi_fee','p.payer','p.cod','p.zone_code','p.zone_name','p.receiver_phone','p.delivery_type','p.delivery_fee','p.driver_total','p.merchant_total');
        //** Filter */
        if($search){
            // $qP->where(function ($q) use ($search){
                $qP->where('p.qr_code',$search)->orWhere('p.receiver_phone','ilike','%'.$search.'%');
            // });
        }

        if($statusId) $qP->where('p.status_id',$statusId);
        // if($driverId) $qP->where('p.driver_id',$driverId);
        if ($driverId) {
            $qP->where(function ($query) use ($driverId) {
                $query->where(function ($subQuery) use ($driverId) {
                    $subQuery->where('p.status_id', '!=', 11)
                            ->where('p.driver_id', $driverId);
                })->orWhere(function ($subQuery) use ($driverId) {
                    $subQuery->where('p.status_id', 11)
                            ->where('p.returned_uid', $driverId);
                });
            });
        }

        if($merchantId) $qP->where('p.merchant_id',$merchantId);
        if($warehouseId) $qP->where('o.warehouse_id',$warehouseId);

        $this->finishPackagePaymentStatus($qP,$driverId,$merchantId,$paymentStatusId);

        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use ($startDate, $endDate,$driverId) {
                $startDateTime = "$startDate 00:00:00";
                $endDateTime = "$endDate 23:59:59";
                // Check for status_id = 9, delivered_datetime should be within the date range
                $q->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('p.status_id', 9)
                    ->whereBetween('p.delivered_datetime', [$startDateTime, $endDateTime]);
                })
                // Check for status_id = 19, failed_datetime should be within the date range
                ->orWhere(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where('p.status_id', 19)
                    ->whereBetween('p.failed_datetime', [$startDateTime, $endDateTime]);
                })
                ->orWhere(function ($q) use ($startDateTime, $endDateTime,$driverId) {
                    $q->where('p.status_id', 11)
                    ->whereBetween('p.returned_datetime', [$startDateTime, $endDateTime]);
                    if($driverId) $q->where('p.returned_uid',$driverId);
                });
            });
        }
        //** --------- */
        // $packages = $qP->get();
        $callbackMapper = function ($qP) use ($lang){
            $qP->arrive_warehouse_datetime = Helper::formatCustomDateTime($qP->arrive_warehouse_datetime,null,false,$lang);
            $qP->has_image = PackageAttachment::where('hidden',0)->where('package_id',$qP->package_id)->value('package_id') ? 1 : 0;
            if($qP->returnUser){
                $qP->driver_name = 'return by '. $qP->returnUser->username;
            }
            if($lang == 'km'){
                $qP->status_code = GeneralSettingService::$statusCodeTrans[$qP->status_id] ?? '';
            }
            if($qP->status_id == 9) $qP->finished_date = Helper::formatCustomDateTime($qP->delivered_datetime,null,false,$lang);
            if($qP->status_id == 11) $qP->finished_date = Helper::formatCustomDateTime($qP->returned_datetime,null,false,$lang);
            if($qP->status_id == 19) $qP->finished_date = Helper::formatCustomDateTime($qP->failed_datetime,null,false,$lang);
            $qP->total = Helper::getNumber(abs($qP->driver_total - $qP->merchant_total),2);
            unset($qP->returnUser);
            return $qP;
        };

        return ApiResponse::PaginationV1($qP,$req,null,[],12000,$callbackMapper,$select,null,['completed_packages']);
    }

    private function finishPackagePaymentStatus($query,$driverId,$merchantId,$paymentStatusId){
        $role = null;

        if ($driverId && !$merchantId) {
            $role = 'driver';
        } elseif ($merchantId && !$driverId) {
            $role = 'merchant';
        }

        if (!$role || !in_array($paymentStatusId, [1, 2])) {
            return; // no filter needed
        }

        $queryMethod = $paymentStatusId == 2 ? 'whereExists' : 'whereNotExists';

        // Payment check
        $query->{$queryMethod}(function ($sub) use ($role) {
            $sub->select(DB::raw(1))
                ->from('payment_packages as pp')
                ->whereColumn('pp.package_id', 'p.id')
                ->where('pp.payer_type', $role)
                ->where('pp.is_deleted', false);
        });

        // Disbursement check
        $query->{$queryMethod}(function ($sub) use ($role) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', $role)
                ->where('dp.is_deleted', false);
        });
        // if($driverId && $paymentStatusId == 2 && !$merchantId){
        //     $query->where(function ($q): void {
        //         $q->whereNotNull('p.driver_payment_id')->orWhereNotNull('p.driver_disbursement_id')->orWhere('dpmt.approved',1)->orWhere('dbur.approved',1);
        //     });
        // }else if($driverId && $paymentStatusId == 1 && !$merchantId){
        //     $query->where(function ($q): void {
        //         $q->whereNull('p.driver_payment_id')->whereNull('p.driver_disbursement_id');
        //     });
        // }else if($merchantId && $paymentStatusId == 2 && !$driverId){
        //     $query->where(function ($q): void {
        //         $q->whereNotNull('p.merchant_payment_id')->orWhereNotNull('p.merchant_disbursement_id')->orWhere('dpmt.approved',1)->orWhere('dbur.approved',1);
        //     });
        // }else if($merchantId && $paymentStatusId == 1 && !$driverId){
        //     $query->where(function ($q): void {
        //         $q->whereNull('p.merchant_payment_id')->whereNull('p.merchant_disbursement_id');
        //     });
        // }

    }

    public function getOneFinishedPackage(Request $req){
        $user = UserService::getAuthUser();
        $packageId = $req->id;
        $package = Package::fromRaw('packages as p')->where('p.company_id',$user->company_id)
        ->leftJoin('users as d','d.id','p.driver_id')
        ->join('tracking_statuses as ts','ts.id','p.status_id')
        ->join('users as m','m.id','p.merchant_id')
        // ->leftJoin('payments as dpmt','dpmt.id','p.driver_payment_id') //** if driver paid or unpaid */
        // ->leftJoin('payments as mpmt','mpmt.id','p.merchant_payment_id') //** if driver paid or unpaid */
        ->orderByDesc('p.id')
        ->whereIn('p.status_id',[9,11,19]) //* delivered and failed with fee
        ->select([
            'p.delivered_datetime','m.username as merchant_name','m.phone as merchant_phone',
            // 'dpmt.approved as approved_driver_pmt',
            // 'mpmt.approved as approved_merchant_pmt',
            'd.username as driver_name','p.status_id','p.id as package_id',
            'd.id as driver_id','p.qr_code','p.price','ts.name as status_code','p.product_type','p.delivered_datetime',
            'p.failed_datetime','p.taxi_fee','p.payer','p.cod','p.zone_code','p.zone_name','p.receiver_phone','p.delivery_type',
            'p.delivery_fee as base_fee','p.driver_total','p.merchant_total','p.extra_charge','p.actual_kg','p.billed_kg',
            'p.receiver_address','p.additional_fee','p.dim_z','p.dim_x','p.dim_y','p.remarks','p.receiver_name'
        ])
        ->where('p.id',$packageId)->first();
        if(!$package) return ApiResponse::NotFound();
        $package->cod = $package->cod ? 1 : 0;
        $deliveryFee = GeneralSettingService::sumDeliveryFee($package->base_fee,$package->extra_charge,$package->taxi_fee,$package->payer);
        $package->delivery_fee = Helper::getNumber($deliveryFee,2);
        return ApiResponse::JsonResult($package);
    }

    public function updatePackage(Request $req){
        $user = UserService::getAuthUser();
        $trxService = new TransactionService();
        return ApiResponse::flex($trxService->updateDeliveryPackage($req,null,$user));
    }

}
