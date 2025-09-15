<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\Mobile\ReusableService;
use App\Services\UserService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    //
    public function getTripPackages(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(ReusableService::getHistoryPackagesV2($req,$user));
    }
}
