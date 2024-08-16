<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VendorType;
use App\Services\UserService;
use Illuminate\Http\Request;

class VendorTypeController extends Controller
{
    //

    private function vendorTypeValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100'
        ]);
    }

    public function createVendorType(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->vendorTypeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());

        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;

        $duplicateName = VendorType::where('branch_id',$user->branch_id)->where('name',$inputs['name'])->first();
        if($duplicateName) return ApiResponse::Duplicated('Type ('.$inputs['name'].') is already exists.');
        $create = VendorType::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function getVendorTypes(Request $req){
        $user = UserService::getAuthUser();
        $rows = VendorType::where('branch_id',$user->branch_id)->get();
        return ApiResponse::Pagination($rows,$req);
    }

    public function getVendorType(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $row = VendorType::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($row);
    }

    public function updateVendorType(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $validate = $this->vendorTypeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());

        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $vendorType = VendorType::where('branch_id',$user->branch_id)->find($id);
        if(!$vendorType) return ApiResponse::NotFound('Vendor type not found');
        $update = $vendorType->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
