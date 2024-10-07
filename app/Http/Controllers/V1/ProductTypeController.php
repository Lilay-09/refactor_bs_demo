<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductType;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProductTypeController extends Controller
{
    //

    private function productTypeValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
        ]);
    }

    public function createProductType(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->productTypeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;

        $create = ProductType::create($inputs);
        if(!$create) return ApiResponse::Error('Fail to create');
        return ApiResponse::JsonResult(null,__('messages.created'));
    }

    public function getProductTypes(Request $req){
        $user = UserService::getAuthUser();
        $query = ProductType::where('is_deleted',0)->where('company_id',$user->company_id);
        $productTypes = $query->get();
        return ApiResponse::Pagination($productTypes,$req,__('messages.get list'));
    }

    public function getOneProductType(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $productType = ProductType::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$productType) return ApiResponse::NotFound(__('messages.not_found'));
        return ApiResponse::JsonResult($productType,false,'Get Product types');
    }

    public function updateProductType(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $productType = ProductType::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$productType) return ApiResponse::NotFound(__('messages.not_found'));
        $validate = $this->productTypeValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $udpate = $productType->update($inputs);
        if(!$udpate) return ApiResponse::Error('Fail to update');
        return ApiResponse::JsonResult(null,false,__('messages.updated'));
    }
}


