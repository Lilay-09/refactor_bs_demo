<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Illuminate\Http\Request;


class GeneralSettingController extends Controller
{
    //
    public function getOptionsDriverFailRemarks(){
        return ApiResponse::JsonResult(GeneralSettingService::optionsDriverRemarks('failure'));
    }

    public function getMerchantFormBooking(){
        $user = UserService::getAuthUser('merchant');
        $obj = (object)[
            'vehicle_types' => GeneralSettingService::optionsVehicleType($user),
            'product_types' => GeneralSettingService::optionsProductType($user)
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getFormOptionsHistory(){
        $user = UserService::getAuthUser('driver');
        $bonusRow = collect([['id' => 0, 'name' => 'All']]);
        $results = GeneralSettingService::optionsTrackingStatus($user,[],[9,10,11],'delivery');
        // Merge the bonus row with the fetched results
        $results = $bonusRow->merge($results);
        $obj = (object)[
            'payment_statuses' => GeneralSettingService::paymentStatus(),
            'statuses' =>$results
        ];
        return ApiResponse::JsonResult($obj);
    }

    public function getOptionsZone(Request $req){
        $user = UserService::getAuthUser('driver');
        return ApiResponse::JsonResult(GeneralSettingService::optionsZone($user));
    }

    public function getZonePrice(Request $req){
        $user = UserService::getAuthUser('driver');
        $id = $req->zone_id;
        return ApiResponse::JsonResult(GeneralSettingService::priceByZone($id,$user));
    }
}
