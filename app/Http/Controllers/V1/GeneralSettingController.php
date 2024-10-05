<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Models\Tax;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Illuminate\Http\Request;

class GeneralSettingController extends Controller
{
    //

    protected $currencyPair = [
        ['name' => 'USD-KHR']
    ];

    protected $gs;

    public function __construct(GeneralSettingService $gs){
        $this->gs = $gs;
    }



    public function getOptionsCountry(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCountry($user));
    }

    public function getOptionsCityByCountry(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCityByCountry($req->country_id,$user));
    }

    public function getOptionsDistrictByCity(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsDistrictByCity($req->city_id,$user));
    }

    public function getOptionsCommuneByDistrict(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonResult($this->gs::optionsCommuneByDistrict($req->district_id,$user));
    }

    // public function getFormUser(){
    //     $user = UserService::getAuthUser();
    //     $obj = (object)[
    //         'roles' => $this->gs::getRoles($user),
    //         'branches' => $this->gs::getBranches($user),
    //     ];
    //     return ApiResponse::JsonResult($obj);
    // }


}
