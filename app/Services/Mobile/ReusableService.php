<?php

namespace App\Services\Mobile;

use App\Models\Delivery;
use DataResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ReusableService
{
    // Your service methods go here
    public static function getHistoryPackages(Request $req,$user=null,$reqSearch=false){
        $paymentStatus = $req->payment_status_id ?? null;
        $statusId = $req->status_id ?? null;
        $search = $req->search ?? null;

        if($reqSearch && !$search) return DataResponse::Pagination(new Collection(),$req);
        $qFp = Delivery::fromRaw('deliveries as d')->join('delivery_packages as dp','d.id','dp.delivery_id')
        ->join('packages as p','p.id','dp.package_id')->orderByDesc('d.id')
        ->join('users as m','m.id','p.merchant_id')
        ->leftJoin('payments as pmt','pmt.id','p.driver_payment_id')
        ->join('tracking_statuses as trs','trs.id','p.status_id')
        ->selectRaw('trs.name as status_code,d.id as delivery_id,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone,p.receiver_name,p.receiver_phone,p.delivery_fee,p.taxi_fee,p.remarks as notes,p.delivery_remarks as remarks,p.id as package_id,p.product_type,p.driver_total as total,p.billed_kg')
        ->whereIn('p.status_id',[9,10,11]);
        if($paymentStatus == 2){
            $qFp->where('pmt.approved',1);
        }
        if($user){
            $qFp->where('d.driver_id',$user->id);
        }
        // else if($paymentStatus == 1) $qFp->where('pmt.approved',0);
        if($statusId) $qFp->where('p.status_id',$statusId);
        if($search) $qFp->where('p.receiver_phone', 'ilike', '%' . $search . '%')
        ->orWhere('m.phone', 'ilike', '%' . $search . '%')
        ->orWhere('d.fleet_tracking_number', 'ilike', '%' . $search . '%');
        $fleetPackages = $qFp->get();
        return DataResponse::Pagination($fleetPackages,$req);
    }
}
