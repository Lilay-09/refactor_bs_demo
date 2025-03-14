<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Mobile\ReusableService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    //
    public function getTripPackages(Request $req){
        return ApiResponse::flex(ReusableService::getHistoryPackages($req,null,true));
    }
}
