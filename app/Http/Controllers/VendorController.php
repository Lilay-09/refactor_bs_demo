<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\UserService;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    //
    private function vendorValidation(Request $req){
        return validator($req->all(),[
            'name' => 'nullable|string|max:50',
            'name_kh' => 'nullable|string|max:150',
            'phone' => 'required|string|min:8|max:25',
            'email' => 'nullable|email',
            'address' => 'nullable|string|max:250',
            'address_kh' => 'nullable|string|max:250',
            'vendor_type_id' => 'required|int|exists:vendor_types,id'
        ]);
    }
    public function createVendor(Request $req){
        $validate = $this->vendorValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = UserService::getAuthUser();
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $duplicatedPhone = Vendor::where('branch_id',$user->branch_id)->where('company_id',$user->company_id)->where('phone',$inputs['phone'])->first();
        if($duplicatedPhone) return ApiResponse::Duplicated('Phone number '.$inputs['phone'].' is already taken.');
        $create = Vendor::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to saved');
    }

    public function getVendors(Request $req){
        $user = UserService::getAuthUser();
        $rows = Vendor::where('branch_id',$user->branch_id)->get();
        return ApiResponse::Pagination($rows,$req);
    }

    public function getVendor(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $row = Vendor::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($row);
    }

    public function updateVendor(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $validate = $this->vendorValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = UserService::getAuthUser();
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $duplicatedPhone = Vendor::where('branch_id',$user->branch_id)->where('id','!=',$id)->where('company_id',$user->company_id)->where('phone',$inputs['phone'])->first();
        if($duplicatedPhone) return ApiResponse::Duplicated('Phone number '.$inputs['phone'].' is already taken.');
        $vendor = Vendor::where('branch_id',$user->branch_id)->find($id);
        if(!$vendor) return ApiResponse::NotFound('Vendor not found');
        $update = $vendor->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
