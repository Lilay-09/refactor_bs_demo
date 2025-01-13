<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Services\UserService;
use Helper;
class TransactionController extends Controller
{
    //
    public function getTransaction(){
        $user = UserService::getAuthUser('merchant');
        // $balanceInfo = Package::where('driver_id',$user->id)
        // ->with(['driver_payment'])
        // ->get();
        $balanceDue = Package::where('merchant_id',$user->id)->where('is_deleted',1)->whereIn('status_id',[9,19])->sum('merchant_total');
        $count = 0;
        $total = 0;
        // $paidTrx = [
        //     [
        //         'payment_date' => '',
        //         'item_count' => 10,
        //         'payment_status' => 'Paid',
        //         'cashier_name' => 'Sam',
        //         'remarks' => '',
        //         'method' => '',
        //         'total' => 20,
        //         'amount' => 20
        //     ],
        //     [
        //         'payment_date' => '',
        //         'item_count' => 10,
        //         'payment_status' => 'Paid',
        //         'cashier_name' => 'Sam',
        //         'remarks' => '',
        //         'method' => '',
        //         'total' => 20,
        //         'amount' => 20
        //     ],
        //     [
        //         'payment_date' => '',
        //         'item_count' => 10,
        //         'payment_status' => 'Paid',
        //         'cashier_name' => 'Sam',
        //         'remarks' => '',
        //         'method' => '',
        //         'total' => 20,
        //         'amount' => 20
        //     ]
        // ];
        $paymentTrx = Payment::where('payer_id',operator: $user->id)
        ->where('payments.is_deleted',0)
        ->where('payments.is_settled',1)
        ->join('users as c','c.id','payments.settled_uid')
        ->selectRaw('payments.id,payments.payment_datetime,payments.payable_amount,payments.is_settled,payments.breakdown_notes,c.user_name as cashier_name,payments.remarks')
        ->get();
        $paymentDetails = PaymentDetail::selectRaw('id,payment_id,method,currency_code')->get();
        $paidTrx = [];
        foreach($paymentTrx as $payment){
            $payment->payment_date = Helper::formatCustomDateTime($payment->payment_datetime,'d-M-Y');
            if($payment->is_settled) {
                $paymentDetails = $this->getPaymentMethods($paymentDetails,$payment->id);
                $payment->breakdown_notes = $paymentDetails->method;
                $paidTrx[] = $payment;
            }
            else {
                $count += 1;
                $total += 1;
            }
            unset($payment->is_settled,$payment->payment_datetime);
        }

        // foreach($balanceInfo as $balance){
        //     $hasPayment = $balance->driver_payment;
        //     if($hasPayment){
        //         if(!$hasPayment->is_settled) $count += 1;
        //     }
        // }

        $obj = (object)[
            'balance_due' => $balanceDue,
            'count' => $count,
            'total' => $total,
            'payment_transaction' => $paidTrx
        ];

        // $payments =

        return ApiResponse::JsonResult($obj);
    }

    public function getPaymentMethods($details,$pmtId){
        $method = null;
        foreach ($details as $d) {
            // Ensure $d is an object before accessing its properties
            if (is_object($d) && isset($d->payment_id) && $d->payment_id == $pmtId) {
                if (!$method) {
                    $method = $d->method;
                } else {
                    $method .= '|' . $d->method;
                }
            }
        }
        return (object)[
            'method' => $method,
        ];
    }

}
