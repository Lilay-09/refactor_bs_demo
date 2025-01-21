<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Services\TransactionService;
use App\Services\UserService;
use Carbon\Carbon;
use Helper;
class TransactionController extends Controller
{
    //
    public function getTransaction(){
        $user = UserService::getAuthUser('merchant');
        // $balanceInfo = Package::where('driver_id',$user->id)
        // ->with(['driver_payment'])
        // ->get();
        // $balanceDue = Package::where('merchant_id',$user->id)->where('is_deleted',1)->whereIn('status_id',[9,19])->sum('merchant_total');
        $count = 0;
        $total = 0;
        $packageInfo = [];
        $payments = Payment::where('payments.is_deleted',0)->where('payments.payer_id',$user->id)->where('payments.approved',1)
        ->join('users as c','c.id','payments.approved_uid')
        ->selectRaw('payments.package_count,payments.id,payments.payable_amount,payments.breakdown_notes,c.user_name as cashier_name,payments.payment_datetime')
        ->orderByDesc('payment_datetime')
        ->get();
        $disbursements = Disbursement::where('type','payment')->where('disbursements.is_deleted',0)->where('disbursements.payee_id',$user->id)->where('disbursements.approved',1)
        ->join('users as c','c.id','disbursements.receiptionist_uid')
        ->selectRaw('disbursements.package_count,disbursements.id,disbursements.payable_amount,disbursements.breakdown_notes,c.user_name as cashier_name,disbursements.payment_datetime')
        ->orderByDesc('payment_datetime')
        ->get();
        $packages = Package::where('is_deleted',0)
        ->where('created_at', '>=', Carbon::now()->subMonths(2))
        ->whereIn('status_id',[9,19])
        ->selectRaw('*')
        ->where('merchant_id',$user->id)
        ->orderBy('merchant_payment_id','desc')
        ->orderBy('merchant_disbursement_id','desc')
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
            if(!isset($samePmtId[$p->merchant_payment_id])){
                $pmt = $this->getTrxDetails($payments,$p->merchant_payment_id);
                if($pmt) {
                    $pmt->payment_status = 'Paid';
                    $pmt->remarks = 'Disbursement';
                    $total -= (float)$pmt->payable_amount;
                    $packageInfo[] = $pmt;
                    $count -= $pmt->package_count;
                }
                $samePmtId[$p->merchant_payment_id] = true;
            }

            if(!isset($sameDisId[$p->merchant_disbursement_id])){
                $dis = $this->getTrxDetails($disbursements,$p->merchant_disbursement_id);
                if($dis) {
                    $dis->payment_status = 'Paid';
                    $total -= (float)$dis->payable_amount;
                    $dis->remarks = 'Receive';
                    $packageInfo[] = $dis;
                    $count -= $dis->package_count;
                }
                $sameDisId[$p->merchant_disbursement_id] = true;
            }
            // else {
            //     $total += Helper::getNumber(TransactionService::getPackageTotal('driver',$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer));
            //     $count +=1;
            // }
            $total += Helper::getNumber(TransactionService::getPackageTotal('driver',$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer));
            $count +=1;
        }
        usort($packageInfo, function ($a, $b) {
            return strtotime($b['payment_datetime']) <=> strtotime($a['payment_datetime']);
        });

        $obj = (object)[
            'balance_due' => (float)Helper::getNumber($total,2),
            'count' => $count,
            'total' => (float)Helper::getNumber($total,2),
            'payment_transaction' => $packageInfo
        ];

        // $payments =

        return ApiResponse::JsonResult($obj);
    }

    private function getTrxDetails($rows,$pmtId){
        foreach($rows as $row){
            if($row->id == $pmtId){
                $row->breakdown_notes = str_replace('|', '&', $row->breakdown_notes);
                $row->payment_date = Helper::dateDMY($row->payment_datetime);
                $row->payment_time = Helper::formatCustomDateTime($row->payment_datetime,'h:i A');
                return $row;
            }
        }
        return null;
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
