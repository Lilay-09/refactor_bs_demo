<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductTag;
use App\Models\ProductVariantTag;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProductTagController extends Controller
{
    //

    public function createProductTag(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:50'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $create = ProductTag::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Create');
        return ApiResponse::Error('Fail to create');
    }

    public function getProductTags(Request $req){
        $user = UserService::getAuthUser();
        $tags = ProductTag::where('branch_id',$user->branch_id)->selectRaw('id,name,name_kh')->get();
        return ApiResponse::Pagination($tags,$req);
    }

    public function productTag(Request $req){
        $user = UserService::getAuthUser();
        $tag = ProductTag::where('branch_id',$user->branch_id)->selectRaw('id,name,name_kh')->find($req->id);
        return ApiResponse::JsonResult($tag);
    }

    public function updateProductTag(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator([
            'id' => $req->id,
            'name' => $req->name,
            'name_kh' => $req->name_kh
        ],[
            'id' => 'required|int',
            'name' => 'required|string|max:50',
            'name_kh' => 'nullable|string|max:50'
        ]);

        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $id = $inputs['id'];
        $tag = ProductTag::where('branch_id',$user->branch_id)->find($id);
        if(!$tag) return ApiResponse::NotFound('Tag not found');
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $update = $tag->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function deleteProductTag(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $tag = ProductVariantTag::where('branch_id',$user->branch_id)->find($id);
        if($tag){
            $tag->delete();
            return ApiResponse::JsonResult(null,false,'Deleted');
        }
        return ApiResponse::NotFound('Tag not found!');
    }
}
