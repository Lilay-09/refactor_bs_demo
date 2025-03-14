<?php

namespace App\Http\Controllers\V1;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Models\DefaultAddress;
use ApiResponse;

class DefaultAddressController
{
    public function DefaultAddressValidation(Request $req){
        return validator($req->all(),[
			'name' => 'required|string|max:250'
		]);
    }
    // Your service methods go here
    public function createDefaultAddress(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->DefaultAddressValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        DefaultAddress::create($inputs);
        return ApiResponse::JsonResult(null,"Created");
    }

    public function updateDefaultAddress (Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $DefaultAddress = DefaultAddress::where('is_deleted',0)->find($id);
        if(!$DefaultAddress) return ApiResponse::NotFound("$DefaultAddress not found");
        $validate = $this->DefaultAddressValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $DefaultAddress->update($inputs);
        return ApiResponse::JsonResult(null,"Updated");
    }

    public function getDefaultAddresses(Request $req){
        // $user = UserService::getAuthUser();
        $DefaultAddress = DefaultAddress::query()->where('is_deleted',0)
        ->selectRaw('id,name,hidden');
        return ApiResponse::PaginationV1($DefaultAddress,$req);
    }

    public function getOneDefaultAddress(Request $req){
        // $user = UserService::getAuthUser();
        $id = $req->id;
        $DefaultAddress = DefaultAddress::where('is_deleted',0)->selectRaw('id,name,hidden')->find($id);
        if(!$DefaultAddress) return ApiResponse::NotFound("Default address not found");
        return ApiResponse::JsonResult($DefaultAddress,"get one Default address");
    }

    public function toggleHiddenDefaultAddress(Request $req){
        // $user = UserService::getAuthUser();
        $id = $req->id;
        $DefaultAddress = DefaultAddress::where('is_deleted',0)->find($id);
        if(!$DefaultAddress) return ApiResponse::NotFound("Default address not found");
        $DefaultAddress->update([
            'hidden' => !$DefaultAddress->hidden
        ]);
        return ApiResponse::JsonResult(null,!$DefaultAddress->hidden ? 'Show' : 'Hide');
    }

    public function deleteDefaultAddress(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $DefaultAddress = DefaultAddress::where('is_deleted',0)->find($id);
        if(!$DefaultAddress) return ApiResponse::NotFound("$DefaultAddress not found");
        $DefaultAddress->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return ApiResponse::JsonResult(null,"Deleted");
    }
}
