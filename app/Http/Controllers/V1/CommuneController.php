<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
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
        if($existCommune) return ApiResponse::Duplicated('Commune ('.$name.') is already exists.');
        $create = Commune::create($inputs);

        if($create) return ApiResponse::JsonResult(null,'Created');

        return ApiResponse::Error('Fail to save country');
    }

    public function getCommunes(Request $req){
        $user = UserService::getAuthUser();
        $cities = Commune::where('is_deleted',0)->where('company_id',$user->company_id)->selectRaw('id,name,name_kh')->orderByDesc('id')->get();
        return ApiResponse::Pagination($cities,$req,'Get communes');
    }

    public function getOneCommune(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $commune = Commune::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        return ApiResponse::JsonResult($commune,'Get on commune');
    }

    public function updateCommune(Request $req){
        $validate = $this->communeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first(),'Please input correct data.');
        $inputs = $validate->validated();
        $name = $inputs['name'];
        $id = $req->id;
        $district_id = $inputs['district_id'];
        $user = UserService::getAuthUser();
        $commune = Commune::find($id)->where('branch_id',$user->branch_id);
        if(!$commune) return ApiResponse::NotFound(__('messages.not_found'));

        $existCity = Commune::where('name',$name)->where('is_deleted',0)->where('company_id',$user->company_id)->where('district_id',$district_id)->where('id','!=',$id)->take(1)->value('id');
        if($existCity) return ApiResponse::Duplicated('Commune('.$name.') is already taken.');
        // return $user;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $commune->update($inputs);
        if($update) return ApiResponse::JsonResult(null,'Update');

        return ApiResponse::Error('Fail to update');
    }

    public function deleteCommune(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $commune = Commune::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$commune) return ApiResponse::NotFound(__('messages.not_found'));
        $commune->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,'Deleted');
    }
}
