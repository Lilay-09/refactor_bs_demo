<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Illuminate\Http\Request;

class GeneralSettingController extends Controller
{
    //
    protected $gs;
    public function __construct(GeneralSettingService $gs){
        $this->gs = $gs;
    }

    // public function getFormProductOption(Request $req){
    //     $user = UserService::getAuthUser();
    //     return ApiResponse::JsonResult($this->gs::getFormProductOptionByType($req->type,$user));
    // }

    public function getFormPurchaseOrder(){
        $user = UserService::getAuthUser();
        $obj = (object)[];
        $obj->options_vendor = $this->gs::getOptionsVendor($user);
        return ApiResponse::JsonResult($obj);
    }

    // public function getOptionProductTypes(Request $req){
    //     return ApiResponse::JsonResult($this->gs::getProductTypes());
    // }
}
