<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductModel;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProductModelController extends Controller
{
    //
    public function createModel(Request $req){
        $validate = validator([
            'name' => $req->name,
            'name_kh' => $req->name_kh,
            'brand_id' => $req->brand_id,
        ],[
            'brand_id' => 'required|int|exists:brands,id',
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $name = $inputs['name'];
        $name_kh = $inputs['name_kh'];
        $brand_id = $inputs['brand_id'];
        $duplicateName = ProductModel::where('brand_id',$brand_id)->where('void',0)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Model ('.$name.') is already taken');
        $create = ProductModel::create([
            'name' => $name,
            'name_kh' => $name_kh,
            'create_uid' => $user->id,
            'brand_id' => $brand_id,
            'update_uid' => $user->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
        ]);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function productModels(Request $req){
        if($req->id) return $this->productModel($req);
        $user = UserService::getAuthUser();
        $search = $req->search;
        $query = ProductModel::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name,name_kh,brand_id');
        if($search){
            $query->where('name','ilike','%'.$search.'%');
        }
        $models = $query->get();
        return ApiResponse::Pagination($models,$req);
    }

    public function productModel(Request $req){
        $user = UserService::getAuthUser();
        $model = ProductModel::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name,name_kh,brand_id')->find($req->id);
        return ApiResponse::JsonResult($model);
    }

    public function updateProductModel(Request $req){
        $validate = validator([
            'id' => $req->id,
            'name' => $req->name,
            'brand_id' => $req->brand_id,
            'name_kh' => $req->name_kh,
        ],[
            'id' => 'required|int',
            'name' => 'required|string|max:50',
            'brand_id' => 'required|int|exists:brands,id',
            'name_kh' => 'nullable|string|max:100'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $id = $inputs['id'];
        $model = ProductModel::where('company_id',$user->company_id)->find($id);
        if(!$model) return ApiResponse::NotFound('Model not found');
        $name = $inputs['name'];
        $name_kh = $inputs['name_kh'];
        $brand_id = $inputs['brand_id'];

        $duplicateName = ProductModel::where('id','!=',$id)->where('brand_id',$brand_id)->where('void',0)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Model ('.$name.') is already taken');

        $update = $model->update([
            'name' => $name,
            'name_kh' => $name_kh,
            'update_uid' => $user->id,
            'brand_id' => $brand_id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
        ]);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function voidModel(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $model = ProductModel::where('branch_id',$user->branch_id)->where('void',0)->find($id);
        if($model){
            $inUse = Product::where('model_id',$id)->first();
            if($inUse) return ApiResponse::ValidateFail('Model is used in product');
            $model->update([
                'void' => 1,
                'void_uid' => $user->id
            ]);
            return ApiResponse::JsonResult(null,false,'Voided');
        }
        return ApiResponse::NotFound('Model not found');
    }

    public function deleteModel(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $model = ProductModel::where('branch_id',$user->branch_id)->find($id);
        if($model){
            $inUse = Product::where('model_id',$id)->first();
            if($inUse) return ApiResponse::ValidateFail('Model is used in product');
            $model->delete();
            return ApiResponse::JsonResult(null,false,'Deleted');
        }
        return ApiResponse::NotFound('Model not found');
    }
}
