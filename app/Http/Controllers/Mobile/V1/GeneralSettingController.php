<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GeneralSettingService;
use App\Services\UserService;

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
}
