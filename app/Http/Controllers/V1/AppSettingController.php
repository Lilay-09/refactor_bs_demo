<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AppSetting;
use App\Services\GeoResolverService;
use App\Services\UserService;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    //

    public function savePrivacyStatement(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(AppSetting::savePrivacyTermCondition($req,'privacy_statement',$user));
    }

    public function saveTermCondition(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(AppSetting::savePrivacyTermCondition($req,'term_condition',$user));
    }

    public function getTermCondition(Request $req){
        $user = UserService::getAuthUser();
        $channel = $req->channel;
        return ApiResponse::flex(AppSetting::getPrivacyTermCondition($channel,'term_condition',$user));
    }

    public function redirectBarcodeScan(Request $req){
        return AppSetting::redirectBasedOnDevice($req);
    }

    public function redirectStoreDriverMobile(Request $req){
        return AppSetting::redirectStoreDriverApp($req);
    }


    public function mapInfo(Request $req){
        $goSolver = new GeoResolverService();
        return ApiResponse::JsonResult($goSolver->fromShortUrl($req->url));
    }

    public function getPrivacyStatement(Request $req){
        $user = UserService::getAuthUser();
        $channel = $req->channel;
        return ApiResponse::flex(AppSetting::getPrivacyTermCondition($channel,'privacy_statement',$user));
    }

    public function redirectCompanyWebsite(Request $req){
        return AppSetting::redirectCompanyWebsite();
    }
}
