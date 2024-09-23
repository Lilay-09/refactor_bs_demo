<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
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

        $duplicateName = VendorType::where('company_id',$user->company_id)->where('void',0)->where('name',$inputs['name'])->first();
        if($duplicateName) {
            $isDiffBranch = $duplicateName->branch_id !== $user->branch_id;
            $diffBranchText = null;
            if($isDiffBranch) $diffBranchText = ' but in another branch';
            return ApiResponse::Duplicated('Type ('.$inputs['name'].') is already exists.'.$diffBranchText);
        }
        $create = VendorType::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function getVendorTypes(Request $req){
        $user = UserService::getAuthUser();
        $rows = VendorType::where('company_id',$user->company_id)->where('void',0)->get();
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
        $vendorType = VendorType::where('branch_id',$user->branch_id)->where('void',0)->find($id);
        if(!$vendorType) return ApiResponse::NotFound('Vendor type not found');
        $validate = $this->vendorTypeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $vendorType->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function voidVendorType(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $vendorType = VendorType::where('company_id',$user->company_id)->where('void',0)->find($id);
        if($vendorType){
            $inUsed = Vendor::where('vendor_type_id',$id)->where('void',0)->first();
            if($inUsed) return ApiResponse::ValidateFail('Vendor type is used by vendor.');
            $vendorType->update([
                'void' => 1,
                'void_uid' => $user->id
            ]);
            return ApiResponse::JsonResult(null,false,'Voided');
        }
        return ApiResponse::NotFound('Vendor type not found');
    }

    public function deleteVendorType(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $vendorType = VendorType::where('branch_id',$user->branch_id)->find($id);
        if($vendorType){
            $inUsed = Vendor::where('vendor_type_id',$id)->first();
            if($inUsed) return ApiResponse::ValidateFail('Vendor type is used by vendor.');
            $vendorType->delete();
            return ApiResponse::JsonResult(null,false,'Deleted');
        }
        return ApiResponse::NotFound('Vendor type not found');
    }
}
