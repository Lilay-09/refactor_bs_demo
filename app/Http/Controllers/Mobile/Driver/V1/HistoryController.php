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
        $fleetPackages = Delivery::fromRaw('deliveries as d')->join('delivery_packages as dp','d.id','dp.delivery_id')
        ->join('packages as p','p.id','dp.package_id')->orderByDesc('d.id')
        ->join('users as m','m.id','p.merchant_id')
        ->selectRaw('d.id as delivery_id,d.fleet_tracking_number,m.user_name as merchant_name,m.phone as merchant_phone')
        ->whereIn('p.status_id',[9,11])
        ->get();

        return ApiResponse::Pagination($fleetPackages,$req);
    }
}
