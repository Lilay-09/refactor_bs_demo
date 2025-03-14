<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DriverCommission;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Services\TransactionService;
use App\Services\UserService;
use Carbon\Carbon;
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
        $payments = Payment::where('payments.is_deleted',0)->where('payments.payer_id',$user->id)
        // ->where('payments.approved',1)
        ->join('users as c','c.id','payments.receiver_uid')
        ->selectRaw('payments.package_count,payments.id,payments.payable_amount,payments.breakdown_notes,c.user_name as cashier_name,payments.payment_datetime')
        ->orderByDesc('payment_datetime')
        ->get();
        $disbursements = Disbursement::where('type','payment')->where('disbursements.is_deleted',0)->where('disbursements.payee_id',$user->id)
        // ->where('disbursements.approved',1)
        ->join('users as c','c.id','disbursements.receiptionist_uid')
        ->selectRaw('disbursements.package_count,disbursements.id,disbursements.payable_amount,disbursements.breakdown_notes,c.user_name as cashier_name,disbursements.payment_datetime')
        ->orderByDesc('payment_datetime')
        ->get();
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
        foreach($packages as $p){
            $price = $p->price;
            $taxiFee = $p->taxi_fee;
            if($p->status_id == 19){
                $price = 0;
                $taxiFee = 0;
            }
            if(!isset($samePmtId[$p->driver_payment_id]) && $p->driver_payment_id){
                $pmt = TransactionService::getTrxDetails($payments,$p->driver_payment_id);
                if($pmt) {
                    $pmt->remarks = 'Disbursement';
                    // $total -= (float)$pmt->payable_amount;
                    $paidTrx[] = $pmt;
                    // $count -= $pmt->package_count;
                }
                $samePmtId[$p->driver_payment_id] = true;
            }

            if(!isset($sameDisId[$p->driver_disbursement_id]) && $p->driver_disbursement_id){
                $dis = TransactionService::getTrxDetails($disbursements,$p->driver_disbursement_id);
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
        $driverCommissions = DriverCommission::where('is_deleted',0)->where('driver_id',$driverId)
        ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
        ->get();
        $driverCommissionInfo = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
        $deliveryCommStartDate = $driverCommissionInfo->normal_delivery_commission_start_date;
        $delCommDatetime = Helper::dateYMD($deliveryCommStartDate). ' 00:00:00';
        $qP = Package::selectRaw('status_id,driver_id')
        ->whereIn('status_id',[9])
        ->where('driver_id',$driverId)
        ->where('is_deleted',0)
        // ->where('driver_disbursement_id',$driverId);
        ->whereNull('driver_disbursement_id');
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            if ($deliveryCommStartDate) {
                $deliveryCommStartDate = date('Y-m-d', strtotime($deliveryCommStartDate));
                if ($startDate < $deliveryCommStartDate) {
                    $startDate = $deliveryCommStartDate;
                }
            }
            $qP->whereBetween('delivered_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);

            if($deliveryCommStartDate) $qP->where('delivered_datetime', '>=', $delCommDatetime);
        }
        if($deliveryCommStartDate){
            $qP->where('delivered_datetime','>=',$delCommDatetime);
            $deliveredCount = $qP->count();
        } else $deliveredCount = 0;
        \Log::error($delCommDatetime);

        $qO = Order::where('is_deleted',0)->where('status_id',5)
        // ->where('driver_disbursement_id',$driverId)
        ->where('driver_id',$driverId);
        if($startDate && $endDate){
            $startDate = date('Y-m-d',strtotime($startDate));
            $endDate = date('Y-m-d',strtotime($endDate));
            $qP->where('order_datetime', '>=', "$startDate 00:00:00")
            ->where('order_datetime', '<=', "$endDate 23:59:59");
        }
        $pickUpCount = $qO->sum('qty');

        $pickUpRate = $driverCommissionInfo->normal_pickup_commission;
        $deliveryRate = $driverCommissionInfo->normal_delivery_commission;
        $total = $pickUpCount * $pickUpRate + $deliveredCount * $deliveryRate;
        $report = [
            "total" => (float)Helper::getNumber($total),
            "details" => [
                [
                    'category' => 'Pickup',
                    'count' => $pickUpCount,
                    'unit' => (float)$pickUpRate,
                    'total' => (float)Helper::getNumber($pickUpCount * $pickUpRate,2),
                    'remarks' => '',
                ],
                [
                    'category' => 'Delivered',
                    'count' => $deliveredCount,
                    'unit' => (float)$deliveryRate,
                    'total' => (float)Helper::getNumber($deliveryRate * $deliveredCount,2),
                    'remarks' => '',
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
        ->selectRaw('id,payable_amount,breakdown_notes as method,receiptionist_uid,payment_datetime')
        ->get();
        foreach($disbursements as $d){
            $d->payment_date = Helper::dateDMY($d->payment_datetime);
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
