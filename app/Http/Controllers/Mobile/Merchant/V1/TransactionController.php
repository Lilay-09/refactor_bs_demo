<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
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
    public function getTransaction(Request $req){
        $user = UserService::getAuthUser('merchant');
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $count = 0;
        $total = 0;
        $packageInfo = [];
        $qP = Payment::where('payments.is_deleted',0)->where('payments.payer_id',$user->id)->where('payments.is_settled',1)
        ->join('users as c','c.id','payments.approved_uid')
        ->selectRaw('payments.remarks,payments.package_count,payments.id,payments.payable_amount,payments.breakdown_notes,c.user_name as cashier_name,payments.payment_datetime')
        ->orderByDesc('payments.payment_datetime');
        $qD = Disbursement::where('disbursements.type','payment')->where('disbursements.is_deleted',0)->where('disbursements.payee_id',$user->id)->where('disbursements.is_settled',1)
        ->join('users as c','c.id','disbursements.receiptionist_uid')
        ->selectRaw('disbursements.remarks,disbursements.package_count,disbursements.id,disbursements.payable_amount,disbursements.breakdown_notes,c.user_name as cashier_name,disbursements.payment_datetime')
        ->orderByDesc('disbursements.payment_datetime');
        if($startDate && $endDate){
            $startDate = Helper::dateYMD($startDate);
            $endDate = Helper::dateYMD($endDate);
            $qP->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('payments.payment_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            });
            $qD->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('disbursements.payment_datetime', ["$startDate 00:00:00", "$endDate 23:59:59"]);
            });
        }
        $disbursements = $qD->get();
        $payments = $qP->get();

        $packages = Package::where('is_deleted',0)
        ->where('created_at', '>=', Carbon::now()->subMonths(3))
        ->whereIn('status_id',[9,19])
        // ->selectRaw('*')
        ->where('merchant_id',$user->id)
        ->orderBy('merchant_payment_id','desc')
        ->orderBy('merchant_disbursement_id','desc')
        ->get();
        $samePmtId = [];
        $sameDisId = [];
        foreach($packages as $key => $p){
            $price = $p->price;
            $taxiFee = $p->taxi_fee;
            if($p->status_id == 19){
                $price = 0;
                $taxiFee = 0;
            }
            if(!isset($samePmtId[$p->merchant_payment_id]) && $p->merchant_payment_id){
                $pmt = TransactionService::getTrxDetails($payments,$p->merchant_payment_id);
                if($pmt) {
                    $pmt->payment_status = 'Paid';
                    $pmt->status = 'Disbursement';
                    $total += ($count > 0 && $key == 0) ?(float)$pmt->payable_amount : 0;
                    $packageInfo[] = $pmt;
                    $count -= ($count > 0 && $key == 0) ? $pmt->package_count : 0;
                }
                $samePmtId[$p->merchant_payment_id] = true;
            }

            if(!isset($sameDisId[$p->merchant_disbursement_id]) && $p->merchant_disbursement_id){
                $dis = TransactionService::getTrxDetails($disbursements,$p->merchant_disbursement_id);
                if($dis) {
                    $dis->payment_status = 'Paid';
                    // \Log::error($total);
                    $total -= ($count > 0 && $key == 0) ? (float)$dis->payable_amount : 0;
                    $dis->status = 'Receive';
                    $packageInfo[] = $dis;
                    // $count -= $dis->package_count;
                    $count -= ($count > 0 && $key == 0) ? $dis->package_count : 0;
                }
                $sameDisId[$p->merchant_disbursement_id] = true;
            }

            if(!$p->merchant_disbursement_id && !$p->merchant_payment_id){
                $total -= Helper::getNumber(TransactionService::getPackageTotal('merchant',$p->cod,$price,$taxiFee,$p->extra_charge,$p->additional_fee,$p->delivery_fee,$p->payer));
                $count +=1;
            }

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
