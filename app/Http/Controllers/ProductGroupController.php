<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
    //

    public function createGroup(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $name = $req->name;
        $duplicateName = ProductGroup::where('void',0)->where('company_id',$user->company_id)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Group ('.$name.') is aleady taken');
        $create = ProductGroup::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function productGroups(Request $req){
        $user = UserService::getAuthUser();
        $search = $req->search;
        $query = ProductGroup::where('company_id',$user->company_id)->where('void',0)->selectRaw('name,id,name_kh');
        if($search){
            $query->where('name','ilike','%'.$search.'%');
        }
        $proGroups = $query->get();
        return ApiResponse::Pagination($proGroups,$req);
    }

    public function productGroup(Request $req){
        $user = UserService::getAuthUser();
        $proGroup = ProductGroup::where('company_id',$user->company_id)->where('void',0)->selectRaw('id,name,name_kh')->find($req->id);
        return ApiResponse::JsonResult($proGroup);
    }

    public function updateGroup(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator([
            'name' => $req->name,
            'name_kh' => $req->name_kh
        ],[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100',
            // 'description' => 'nullable|string|max:250',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;

        $id = $req->id;
        $name = $req->name;
        $name_kh = $req->name_kh;
        $productGroup = ProductGroup::where('company_id',$user->company_id)->find($id);
        if(!$productGroup) return ApiResponse::NotFound('Group not found');
        $duplicateName = ProductGroup::where('void',0)->where('id','!=',$id)->where('company_id',$user->company_id)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Group ('.$name.') is aleady taken');

        // $description = $req->description;
        $update = $productGroup->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function voidGroup(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $productGroup = ProductGroup::where('company_id',$user->company_id)->where('void',0)->find($id);
        if($productGroup){
            $inUsed = Product::where('group_id',$id)->first();
            if($inUsed) return ApiResponse::ValidateFail('Product group is used in product.');
            $productGroup->update([
                'void' => 1,
                'void_uid' => $user->id
            ]);
            return ApiResponse::JsonResult(null,false,'Voided');
        }
        return ApiResponse::NotFound('Product group not found');
    }

    public function deleteGroup(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $productGroup = ProductGroup::where('company_id',$user->company_id)->where('void',0)->find($id);
        if($productGroup){
            $inUsed = Product::where('group_id',$id)->first();
            if($inUsed) return ApiResponse::ValidateFail('Product group is used in product.');
            $productGroup->delete();
            return ApiResponse::JsonResult(null,false,'Deleted');
        }
        return ApiResponse::NotFound('Product group not found');
    }
}


