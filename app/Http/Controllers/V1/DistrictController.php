<?php

namespace App\Http\Controllers\V1;
use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Commune;
use App\Models\District;
use App\Services\UserService;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    //

    function districtValidation(Request $req){
        return validator($req->all(),[
            'name_en' => 'required|string|max:50',
            'name_km' => 'nullable|string|max:100',
            'city_id' => 'required|int|exists:cities,id'
        ]);
    }
    public function createDistrict(Request $req){
        $validate = $this->districtValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $existDistrict = District::where('city_id',$inputs['city_id'])->where('is_deleted',0)
        ->where('name_en',$inputs['name_en'])->first();
        if($existDistrict) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'District ('.$inputs['name_en'].') is already exists'
        ]));
        $create = District::create($inputs);
        if($create) return ApiResponse::JsonResult(null,'Created');
        return ApiResponse::Error('failed to create');
    }


    public function getDistricts(Request $req){
        $user = UserService::getAuthUser();
        $districts = District::where('company_id',$user->company_id)->where('is_deleted',0)
        ->orderByDesc('id')
        ->selectRaw('name_en,id,name_km,city_id')->get();
        return ApiResponse::Pagination($districts,$req,'get all districts');
    }

    public function getCommunesByDistrict(Request $req){
        $user = UserService::getAuthUser();
        $district_id = $req->id;
        $communes = Commune::where('is_deleted',0)->where('district_id',$district_id)->get();
    }

    public function district(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $districts = District::where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('name_en,id,name_km,city_id')->find($id);
        if(!$districts) return ApiResponse::NotFound(__('messages.not_found'));
        return ApiResponse::JsonResult($districts,'get one district');
    }


    public function updateDistrict(Request $req){
        $validate = $this->districtValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $id = $req->id;
        $district = District::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$district) return ApiResponse::NotFound(__('messages.not_found'));
        $existDistrict = District::where('city_id',$inputs['city_id'])->where('id','!=',$id)->where('is_deleted',0)
        ->where('name_en',$inputs['name_en'])->first();
        if($existDistrict) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'District ('.$inputs['name_en'].') is already exists'
        ]));
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $district->update($inputs);
        if($update) return ApiResponse::JsonResult(null,'Updated');
        return ApiResponse::Error('failed to update');
    }

    public function deleteDistrict(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $district = District::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$district) return ApiResponse::NotFound(__('messages.not_found'));
        $district->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,'Deleted');
    }
}
