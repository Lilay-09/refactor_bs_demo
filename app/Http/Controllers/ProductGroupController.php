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
        $validate = validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $name = $req->name;
        $name_kh = $req->name_kh;
        // $description = $req->description;
        $user = UserService::getAuthUser();

        $create = ProductGroup::create([
            'name' => $name,
            'name_kh' => $name_kh,
            'create_uid' => $user->id,
            'update_uid' => $user->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ]);

        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function productGroups(Request $req){
        if($req->id) return $this->productGroup($req);
        $user = UserService::getAuthUser();
        $search = $req->search;
        $query = ProductGroup::where('company_id',$user->company_id)->selectRaw('name,id,name_kh');
        if($search){
            $query->where('name','ilike','%'.$search.'%');
        }
        $proGroups = $query->get();
        return ApiResponse::Pagination($proGroups,$req);
    }

    public function productGroup(Request $req){
        $user = UserService::getAuthUser();
        $proGroup = ProductGroup::where('company_id',$user->company_id)->selectRaw('id,name,name_kh')->find($req->id);
        return ApiResponse::JsonResult($proGroup);
    }

    public function updateGroup(Request $req){
        $validate = validator([
            'id' => $req->id,
            'name' => $req->name,
            'name_kh' => $req->name_kh
        ],[
            'id' => 'required|int',
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:100',
            // 'description' => 'nullable|string|max:250',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = UserService::getAuthUser();
        $id = $req->id;
        $productGroup = ProductGroup::where('company_id',$user->company_id)->find($id);
        if(!$productGroup) return ApiResponse::NotFound('Group not found');
        $name = $req->name;
        $name_kh = $req->name_kh;
        // $description = $req->description;
        $update = $productGroup->update([
            'name' => $name,
            'name_kh' => $name_kh,
            // 'description' => $description,
            'update_uid' => $user->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ]);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function deleteGroup(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $productGroup = ProductGroup::where('company_id',$user->company_id)->find($id);
        if($productGroup){
            $inUsed = Product::where('group_id',$id)->first();
            if($inUsed) return ApiResponse::ValidateFail('Product group is used in product.');
            $productGroup->delete();
        }
        return ApiResponse::NotFound('Product group not found');
    }
}


