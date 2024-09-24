<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ProductModel;
use App\Services\UserService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    //

    function brandValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100'
        ]);
    }
    public function createBrand(Request $req){

        $validate = $this->brandValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $create = Brand::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function brands(Request $req){
        $user = UserService::getAuthUser();
        $query = Brand::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name,name_kh');
        if($req->search){
            $query->where('name','ilike','%'.$req->search.'%')->orWhere('name_kh','ilike','%'.$req->search.'%');
        }
        $brands = $query->get();
        return ApiResponse::Pagination($brands,$req);
    }
    public function brand(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $brand = Brand::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name,name_kh')->find($id);
        return ApiResponse::JsonResult($brand);
    }

    public function updateBrand(Request $req,$id=null){
        $validate = $this->brandValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $brand = Brand::where('company_id',$user->company_id)->where('void',0)->find($id);
        if(!$brand) return ApiResponse::NotFound('Brand not found');
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $brand->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function voidBrand(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $brand = Brand::where('company_id',$user->company_id)->where('void',0)->find($id);
        if($brand){
            $inUse = ProductModel::where('brand_id',$id)->where('void',0)->where('branch_id',$user->branch_id)->first();
            if($inUse) return ApiResponse::ValidateFail('Brand is used in model');
            $brand->update([
                'void' => 1,
                'void_uid' => $user->id
            ]);
            return ApiResponse::JsonResult(null,false,'Voided');
        }
        return ApiResponse::NotFound('Brand not found');
    }

    public function deleteBrand(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $brand = Brand::where('company_id',$user->company_id)->where('')->find($id);
        if($brand){
            $inUse = ProductModel::where('brand_id',$id)->where('void',0)->where('branch_id',$user->branch_id)->first();
            if($inUse) return ApiResponse::ValidateFail('Brand is used in model');
            $brand->delete();
            return ApiResponse::JsonResult(null,false,'Deleted');
        }
        return ApiResponse::NotFound('Brand not found');
    }
}
