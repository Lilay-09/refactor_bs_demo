<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Commune;
use App\Services\UserService;
use Illuminate\Http\Request;

class CommuneController extends Controller
{
    //

    function communeValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string',
            'district_id' => 'required|int',
            'name_kh' => 'nullable|string'
        ]);
    }
    public function createCommune(Request $req){
        $validate = $this->communeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $name = $inputs['name'];
        $district_id = $inputs['district_id'];
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $existCommune = Commune::where('name',$name)->where('company_id',$user->company_id)->where('is_deleted',0)->where('district_id',$district_id)->take(1)->value('id');
        if($existCommune) return ApiResponse::Duplicated('City ('.$name.') is already exists.');
        $create = Commune::create($inputs);

        if($create) return ApiResponse::JsonResult(null,false,'Created');

        return ApiResponse::JsonResult([
            'error' => true,
            'message' => 'Fail to save country'
        ],500);
    }

    public function getCommunes(Request $req){
        $user = UserService::getAuthUser();
        $cities = Commune::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('id,name,name_kh')->get();
        return ApiResponse::Pagination($cities,$req,'Get cities');
    }

    public function getOneCommune(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $city = Commune::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        return ApiResponse::JsonResult($city,false,'Get on city');
    }

    public function updateCommune(Request $req,$id=null){
        $validate = $this->communeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first(),'Please input correct data.');
        $inputs = $validate->validated();
        $name = $inputs['name'];
        $id = $id ? $id :$req->id;
        $country_id = $inputs['country_id'];
        $user = UserService::getAuthUser();
        $commune = Commune::find($id)->where('branch_id',$user->branch_id);
        if(!$commune) return ApiResponse::NotFound(__('messages.not_found'));

        $existCity = City::where('name',$req->name)->where('is_deleted',0)->where('company_id',$user->company_id)->where('country_id',$country_id)->where('id','!=',$id)->take(1)->value('id');
        if($existCity) return ApiResponse::Duplicated('Commune('.$name.') is already taken.');
        // return $user;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $commune->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Update');

        return ApiResponse::Error('Fail to update');
    }

    public function voidCommune(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $commune = Commune::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$commune) return ApiResponse::NotFound(__('messages.not_found'));
        $commune->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,false,'Deleted');
    }
}
