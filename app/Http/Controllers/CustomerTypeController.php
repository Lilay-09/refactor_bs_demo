<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomerType;
use App\Services\UserService;
use Illuminate\Http\Request;

class CustomerTypeController extends Controller
{
    //
    private function vendorTypeValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100'
        ]);
    }

    public function createCustomerType(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->vendorTypeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());

        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;

        $duplicateName = CustomerType::where('company_id',$user->company_id)->where('name',$inputs['name'])->first();
        if($duplicateName) {
            $isDiffBranch = $duplicateName->branch_id !== $user->branch_id;
            $diffBranchText = null;
            if($isDiffBranch) $diffBranchText = ' but in another branch';
            return ApiResponse::Duplicated('Type ('.$inputs['name'].') is already exists.'.$diffBranchText);
        }
        $create = CustomerType::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function getCustomerTypes(Request $req){
        $user = UserService::getAuthUser();
        $rows = CustomerType::where('branch_id',$user->branch_id)->get();
        return ApiResponse::Pagination($rows,$req);
    }

    public function getCustomerType(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $row = CustomerType::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($row);
    }

    public function updateCustomerType(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $validate = $this->vendorTypeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());

        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $vendorType = CustomerType::where('branch_id',$user->branch_id)->find($id);
        if(!$vendorType) return ApiResponse::NotFound('Vendor type not found');
        $update = $vendorType->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
