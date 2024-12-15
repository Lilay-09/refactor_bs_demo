<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PriceListname;
use App\Services\UserService;
use Illuminate\Http\Request;

class PriceListNameController extends Controller
{
    //
    private function priceListNameValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'kg_marker' => 'nullable|numeric'
        ]);
    }
    public function createPriceListName(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->priceListNameValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $existsName = PriceListname::where('name',$inputs['name'])->where('is_deleted',0)->first();
        if($existsName) return ApiResponse::Duplicated(__('messages.error',['info' => 'Price List Name ('.$inputs['name'].') has already taken']));
        $create = PriceListname::create($inputs);
        if(!$create) return ApiResponse::Error(__('messages.error',['info' => 'Fail to create price list']));
        return ApiResponse::JsonResult(null,__('messages.created',['info' => 'Price list']));
    }

    public function getOnePriceListName(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceListName = PriceListname::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$priceListName) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Price List']));
        return ApiResponse::JsonResult($priceListName,__('messages.get one',['info' => 'Price list']));
    }

    public function deletePriceListName(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceListName = PriceListname::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$priceListName) return ApiResponse::NotFound();
        $priceListName->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null);
    }

    public function updatePriceListName(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceListName = PriceListname::where('is_deleted',0)->find($id);
        if(!$priceListName) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Price List']));
        $validate = $this->priceListNameValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $existsName = PriceListname::where('id','!=',$id)->where('company_id',$user->company_id)->where('is_deleted',0)->where('name',$inputs['name'])->first();
        if($existsName) return ApiResponse::Duplicated(__('messages.error',['info' => 'Price List Name ('.$inputs['name'].') has already taken']));
        $priceListName->update($inputs);
        return ApiResponse::JsonResult(null,__('messages.created',['info' => 'Price list']));
    }
}
