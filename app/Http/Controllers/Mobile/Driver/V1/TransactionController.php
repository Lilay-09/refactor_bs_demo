<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Services\GeneralSettingService;
use App\Services\TransactionService;
use App\Services\UserService;
use Carbon\Carbon;
use DB;
use Helper;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    //

    public function getTransactionSummary(Request $req){
        $user = UserService::getAuthUser('driver');
        // $balanceDue = Package::where('driver_id',$user->id)->where('is_deleted',0)->whereIn('status_id',[9,19])->sum('driver_total');
        $count = 0;
        $total = 0;
        $paidTrx = [];
        $startDate = $req->query('startDate');
        $endDate = $req->query('endDate');
        $qPmt = Payment::where('payments.is_deleted',0)->where('payments.payer_id',$user->id)
        // ->where('payments.approved',1)
        ->join('users as c','c.id','payments.receiver_uid')
        ->selectRaw('payments.package_count,payments.id,payments.payable_amount,payments.breakdown_notes,c.user_name as cashier_name,payments.payment_datetime')
        ->orderByDesc('payment_datetime');

        $qDis = Disbursement::where('type','payment')->where('disbursements.is_deleted',0)->where('disbursements.payee_id',$user->id)
        // ->where('disbursements.approved',1)
        ->join('users as c','c.id','disbursements.receiptionist_uid')
        ->selectRaw('disbursements.package_count,disbursements.id,disbursements.payable_amount,disbursements.breakdown_notes,c.user_name as cashier_name,disbursements.payment_datetime')
        ->orderByDesc('payment_datetime');

        if($startDate && $endDate){
            $startDateTime = Helper::dateYMD($startDate).' 00:00:00';
            $endDateTime = Helper::dateYMD($endDate).' 23:59:59';
            $qPmt->whereBetween('payment_datetime',[$startDateTime,$endDateTime]);
            $qDis->whereBetween('payment_datetime',[$startDateTime,$endDateTime]);
        }

        $disbursements = $qDis->get();

        $payments = $qPmt->get();
        $packages = Package::where('is_deleted',0)
        ->where('created_at', '>=', Carbon::now()->subMonths(2))
        ->whereIn('status_id',[9,19])
        ->selectRaw('*')
        ->where('driver_id',$user->id)
        ->orderBy('driver_payment_id','desc')
        ->orderBy('driver_disbursement_id','desc')
        ->get();
        $samePmtId = [];
        $sameDisId = [];
        $paymentDetails = PaymentDetail::whereIn('payment_id',Helper::pluckEloCollection($payments,'id'))->get()->keyBy('payment_id');
        $disbursementDetails = DisbursementDetails::whereIn('disbursement_id',Helper::pluckEloCollection($disbursements,'id'))->get()->keyBy('payment_id');
        foreach($packages as $p){
            $price = $p->price;
            $taxiFee = $p->taxi_fee;
            if($p->status_id == 19){
                $price = 0;
                $taxiFee = 0;
            }
            if(!isset($samePmtId[$p->driver_payment_id]) && $p->driver_payment_id){
                $pmt = TransactionService::getTrxDetails($payments,$p->driver_payment_id,$paymentDetails);
                if($pmt) {
                    $pmt->remarks = 'Disbursement';
                    // $total -= (float)$pmt->payable_amount;
                    $paidTrx[] = $pmt;
                    // $count -= $pmt->package_count;
                }
                $samePmtId[$p->driver_payment_id] = true;
            }

            if(!isset($sameDisId[$p->driver_disbursement_id]) && $p->driver_disbursement_id){
                $dis = TransactionService::getTrxDetails($disbursements,$p->driver_disbursement_id,$disbursementDetails);
                if($dis) {
                    // $total -= (float)$dis->payable_amount;
                    $dis->remarks = 'Receive';
                    $paidTrx[] = $dis;
                    // $count -= $dis->package_count;
                }
                $sameDisId[$p->driver_disbursement_id] = true;
            }

            // else {
            //     $total += Helper::getNumber(TransactionService::getPackageTotal('driver',$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer));
            //     $count +=1;
            // }
            if(!$p->driver_payment_id && !$p->driver_disbursement_id) {
                $count += 1;
                $total += Helper::getNumber(TransactionService::getPackageTotal('driver',$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer));
            }


        }
        usort($paidTrx, function ($a, $b) {
            return strtotime($b['payment_datetime']) <=> strtotime($a['payment_datetime']);
        });

        $obj = (object)[
            'balance_due' => (float)Helper::getNumber($total,2),
            'count' => $count,
            'total' => (float)Helper::getNumber($total,2),
            'payment_transaction' => $paidTrx
        ];

        // $payments =

        return ApiResponse::JsonResult($obj);
    }

    public function getUnpaidPackages(Request $req){
        $user = UserService::getAuthUser();
        // $type = 'driver';
        $lang = $req->lang;
        $qP = Package::query()->from('packages as p')->where('p.is_deleted',0)
        ->join('users as m','m.id','p.merchant_id')
        ->join('tracking_statuses as trs','p.status_id','trs.id')
        ->where('p.driver_id',$user->id)
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('payment_packages as pp')
                ->whereColumn('pp.package_id', 'p.id')
                ->where('pp.payer_type', 'driver')
                ->where('pp.is_deleted', false);
        })
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', 'driver')
                ->where('dp.is_deleted', false);
        })

        ->selectRaw('p.driver_id,p.returned_uid,p.payer,p.extra_charge,p.cod,p.price,p.pickup_notes as notes,p.merchant_total,p.receiver_address,p.qr_code,p.status_id,trs.name as status_code,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks,p.id as package_id,p.product_type,p.driver_total,p.billed_kg,p.failed_datetime,p.delivered_datetime,p.arrive_warehouse_datetime,p.returned_datetime');
        // ->get();
        $callback = function ($p) use($lang){
            if($lang == 'km'){
                $p->status_code = GeneralSettingService::$statusCodeTrans[$p->status_id];
            }
            return $p;
        };
        return ApiResponse::PaginationV1($qP,$req,'',[],200,$callback);
    }

    // public function getPaymentMethods($details,$pmtId){
    //     $method = null;
    //     foreach ($details as $d) {
    //         // Ensure $d is an object before accessing its properties
    //         if (is_object($d) && isset($d->payment_id) && $d->payment_id == $pmtId) {
    //             if (!$method) {
    //                 $pMtd = $d->method;
    //                 if($d->currency_code == 'KHR'){
    //                     $pMtd = $pMtd.':KHR';
    //                 }
    //                 $method = $pMtd;
    //             } else {
    //                 $pMtd = $d->method;
    //                 if($d->currency_code == 'KHR'){
    //                     $pMtd = $pMtd.':KHR';
    //                 }
    //                 $method .= '|' . $pMtd;
    //             }
    //         }
    //     }
    //     return (object)[
    //         'method' => $method,
    //     ];
    // }



    public function getCommissionReport(Request $req){
        $user = UserService::getAuthUser('driver');
        $driverId = $user->id;
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $driverCommissions = DriverCommission::where('driver_id',$user->id)->where('is_deleted',0)
        ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
        ->get();
        $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
        $deliveryCommStartDate = $driverCommissionInfo->normal_delivery_commission_start_date;
        $delCommDatetime = Helper::dateYMD($deliveryCommStartDate). ' 00:00:00';
        $qP = Package::selectRaw('id,status_id,driver_id,driver_disbursement_id')
        ->whereIn('status_id',[9])
        ->where('driver_id',$driverId)
        ->where('is_deleted',0)
        // ->where('driver_disbursement_id',$driverId);
        ->whereNull('driver_commission_id');
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            if ($deliveryCommStartDate) {
                $deliveryCommStartDate = date('Y-m-d', strtotime($deliveryCommStartDate));
                if ($startDate < $deliveryCommStartDate) {
                    $startDate = $deliveryCommStartDate;
                }
            }
            // \Log::error($startDate.'--'.$endDate);
            $qP->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            // $qP->where('delivered_datetime','>=',$delCommDatetime);
        }
        if($deliveryCommStartDate){
            // $qP->where('delivered_datetime','>=',$delCommDatetime);
            $deliveredCount = $qP->count();
        } else $deliveredCount = 0;
        // $qO = Order::where('is_deleted',0)->where('status_id',5)
        // ->whereNull('driver_commission_id')
        // // ->where('driver_disbursement_id',$driverId)
        // ->where('driver_id',$driverId);
        // if($startDate && $endDate){
        //     $startDate = date('Y-m-d',strtotime($startDate));
        //     $endDate = date('Y-m-d',strtotime($endDate));
        //     $qP->where('order_datetime', '>=', "$startDate 00:00:00")
        //     ->where('order_datetime', '<=', "$endDate 23:59:59");
        // }
        $pickUpCount = 0;//$qO->sum('qty');

        $pickUpRate = $driverCommissionInfo->normal_pickup_commission;
        $deliveryRate = $driverCommissionInfo->normal_delivery_commission;
        $total = $pickUpCount * $pickUpRate + $deliveredCount * $deliveryRate;
        $report = [
            "total" => (float)Helper::getNumber($total),
            "details" => [
                [
                    'category' => 'Pickup',
                    'details' => [
                        [
                            'count' => $pickUpCount,
                            'unit' => (float)$pickUpRate,
                            'total' => (float)Helper::getNumber($pickUpCount * $pickUpRate,2),
                            'remarks' => '',
                        ]
                    ]
                ],
                [
                    'category' => 'Delivered',
                    'details' => [
                        [
                            'type' => 'Normal',
                            'count' => $deliveredCount,
                            'unit' => (float)$deliveryRate,
                            'total' => (float)Helper::getNumber($deliveryRate * $deliveredCount,2),
                            'remarks' => '',
                        ],
                        [
                            'type' => 'Fast',
                            'count' => $deliveredCount,
                            'unit' => (float)$deliveryRate,
                            'total' => (float)Helper::getNumber($deliveryRate * $deliveredCount,2),
                            'remarks' => '',
                        ]
                    ]
                ]
            ]
        ];

        return ApiResponse::JsonResult($report);
    }

    public function getCommissionTrx(){
        $user = UserService::getAuthUser('driver');
        $disbursements = Disbursement::where('payee_type','driver')
        ->where('type','commission')
        ->where('is_deleted',0)
        ->with('receiptionist:id,user_name')
        ->where('payee_id',$user->id)
        ->selectRaw('id,payable_amount,breakdown_notes as method,receiptionist_uid,payment_datetime,remarks')
        ->get();
        foreach($disbursements as $d){
            $d->payment_date = Helper::dateDMY($d->payment_datetime);
            $d->payment_time = Helper::dateDMY($d->payment_datetime,'h:i A');
            $d->payer_name = $d->receiptionist->user_name;
            $d->payable_amount = (float)$d->payable_amount;
            unset($d->receiptionist,$d->receiptionist_uid,$d->payment_datetime);
        }
        return ApiResponse::JsonResult($disbursements);
    }

    // public function getCommissonTranxAndReport(Request $req){
    //     $startDate = $req->startDate;
    //     $endDate = $req->endDate;
    //     $obj = (object)[
    //         'report' => [
    //             [
    //                 'category' => '',
    //                 'count' => 250,
    //                 'unit' => 0.5,
    //                 'total' => 0,
    //                 'remarks' =>  ''
    //             ]
    //         ],
    //         'transaction' => [
                    // [
                    //     'payment_date' => '',
                    //     'payable_amount' => 0,
                    //     'method' => '',
                    //     'payer_name' => '',
                    // ]
    //         ],
    //     ];

    //     return ApiResponse::JsonResult($obj);
    // }
}
