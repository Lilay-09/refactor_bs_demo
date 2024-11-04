<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GeneralSettingService;

class GeneralSettingController extends Controller
{
    //
    public function getOptionsDriverFailRemarks(){
        return ApiResponse::JsonResult(GeneralSettingService::optionsDriverRemarks('failure'));
    }
}
