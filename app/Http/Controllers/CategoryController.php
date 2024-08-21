<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AppSetting;
use App\Services\UserService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //

    public function categoryValidation(Request $req){
        return validator([
            'name' => $req->name,
            'name_kh' => $req->name_kh
        ],[
            'name' => 'required|string:max:50',
            'name_kh' => 'nullable|string|max:100'
        ]);
    }

    public function createCategory(Request $req){
        $validate = $this->categoryValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $name = $inputs['name'];
        $name_kh = $inputs['name_kh'];
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $duplicateName = Category::where('company_id',$user->company_id)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Category ('.$name.') is already exists.');
        $create = Category::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function categories(Request $req){
        $user = UserService::getAuthUser();
        $query = Category::where('branch_id',$user->branch_id)->selectRaw('id,name,name_kh');
        if($req->search){
            $query->where('name','ilike','%'.$req->search.'%')->orWhere('name_kh','ilike','%'.$req->search.'%');
        }
        $categories = $query->get();
        return ApiResponse::Pagination($categories,$req);
    }

    public function category(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $category = Category::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($category);
    }

    // public function createSubCategory(){

    // }


    public function updateCategory(Request $req,$id=null){
        $validate = $this->categoryValidation($req);

        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $id = $id ?$id:$req->id;
        $user = UserService::getAuthUser();
        $category = Category::where('branch_id',$user->branch_id)->find($id);
        $name = $inputs['name'];
        $name_kh = $inputs['name_kh'];
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $duplicateName = Category::where('company_id',$user->company_id)->where('id','!=',$id)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Category ('.$name.' is already exists.)');
        $update = $category->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
