<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    //
    public function getHistoryPackages(Request $req){
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;
        $qFp = Delivery::fromRaw('deliveries as d')->join('delivery_packages as dp','d.id','dp.delivery_id')
        ->join('packages as p','p.id','dp.package_id')->orderByDesc('d.id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('trs.name as status_code,d.id as delivery_id,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks as notes,p.delivery_remarks as remarks,p.id as package_id,p.product_type,p.driver_total as total')
        ->whereIn('p.status_id',[9,10,11]);
        if($paymentStatus == 2){
            $qFp->where('pmt.approved',1);
        }else if($paymentStatus == 1) $qFp->where('pmt.approved',0);
        if($statusId) $qFp->where('p.status_id',$statusId);
        if($search) $qFp->where('p.receiver_phone', 'ilike', '%' . $search . '%');
        $fleetPackages = $qFp->get();
        return ApiResponse::Pagination($fleetPackages,$req);
    }
}
