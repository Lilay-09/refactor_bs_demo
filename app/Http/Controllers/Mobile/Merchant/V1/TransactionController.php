<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\Package;
use App\Models\Payment;
use App\Services\AppSetting;
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
    public function getTransaction(Request $req){
        $user = UserService::getAuthUser('merchant');
        $startDate = $req->startDate;
        $endDate = $req->endDate;
        $count = 0;
        $total = 0;
        $packageInfo = [];
        $qP = Payment::where('payments.is_deleted',0)->where('payments.payer_id',$user->id)->where('payments.is_settled',1)
        ->join('users as c','c.id','payments.approved_uid')
        ->selectRaw('payments.remarks,payments.package_count,payments.id,payments.payable_amount,payments.breakdown_notes,c.username as cashier_name,payments.payment_datetime')
        ->orderByDesc('payments.payment_datetime');
        $qD = Disbursement::where('disbursements.type','payment')->where('disbursements.is_deleted',0)->where('disbursements.payee_id',$user->id)->where('disbursements.is_settled',1)
        ->join('users as c','c.id','disbursements.receiptionist_uid')
        ->selectRaw('disbursements.remarks,disbursements.package_count,disbursements.id,disbursements.payable_amount,disbursements.breakdown_notes,c.username as cashier_name,disbursements.payment_datetime')
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


    public function getUnpaidPackages(Request $req){
        $user = UserService::getAuthUser();
        $fields = [
            'p.id', 'p.status_id', 'p.merchant_id', 'p.receiver_phone', 'p.receiver_address', 'p.receiver_name',
            'p.cod', 'p.price', 'p.delivery_fee', 'p.remarks', 'p.driver_id',
            'p.arrive_warehouse_datetime', 'delivery_remarks as notes',
            'p.delivered_datetime', 'p.failed_datetime', 'p.returned_datetime', 'p.updated_at','st.name as status_name'
        ];
        $qp = Package::query()->from('packages as p')->where('p.is_deleted',false)
        ->join('tracking_statuses as st','st.id','p.status_id')
        ->where('p.merchant_id',$user->id)
        ->whereIn('p.status_id',[9,19]);
        $qp->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('payment_packages as pp')
                ->whereColumn('pp.package_id', 'p.id')
                ->where('pp.payer_type', 'merchant')
                ->where('pp.is_deleted', false);
        })->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.payee_type', 'merchant')
                ->where('dp.type','payment')
                ->where('dp.is_deleted', false);
        });
        $lang = $req->lang;
        $callback = function ($pkg, $statusCode) use ($lang) {
            $pkg->price = (float) $pkg->price;
            $pkg->cod_fee = $pkg->cod ? $pkg->price : 0;
            $pkg->status_code = $statusCode;
            $pkg->driver_phone = $pkg->driver->phone ?? null;
            $pkg->driver_name = $pkg->driver->username ?? null;
            $pkg->total = $pkg->cod_fee;
            $pkg->delivery_fee = (float) $pkg->delivery_fee;
            $pkg->fee = $pkg->delivery_fee;
            $pkg->has_img = isset($attachments[$pkg->package_id]);
            $pkg->render_status = $lang == 'km' ? GeneralSettingService::$statusCodeTrans[$pkg->status_id] : $pkg->status_name;
            $pkg->telegram_url = AppSetting::getTelegramLink('merchant',$pkg->receiver_phone,$pkg->driver?->phone);
            // foreach ($customDateFields as $field) {
            //     if (!empty($pkg->$field)) {
            //         $pkg->$field = Helper::formatCustomDateTime($pkg->$field);
            //     }
            // }
            if($pkg->status_id == 9){
                $pkg->arrive_warehouse_date = Helper::formatCustomDateTime($pkg->delivered_datetime,'d/m/Y');
                $pkg->finished_time = Helper::formatCustomDateTime($pkg->delivered_datetime,'h:i A');
            }else if($pkg->status_id == 19){
                $pkg->finished_date = Helper::formatCustomDateTime($pkg->failed_datetime,'d/m/Y');
                $pkg->finished_time = Helper::formatCustomDateTime($pkg->failed_datetime,'h:i A');
            }
            $pkg->arrive_warehouse_date = Helper::formatCustomDateTime($pkg->arrive_warehouse_datetime,'d/m/Y');
            $pkg->arrive_warehouse_time = Helper::formatCustomDateTime($pkg->arrive_warehouse_datetime,'h:i A');

            unset($pkg->driver, $pkg->status);
            return $pkg;
        };
        return ApiResponse::PaginationV1($qp,$req,'',[],100,$callback,$fields);
    }
}
