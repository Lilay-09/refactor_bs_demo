<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Services\UserService;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    //
    function taxValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:35',
            'amount' => 'required|numeric',
        ]);
    }
    public function create(Request $req){
        $validate = $this->taxValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $name = $inputs['name'];
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $existTaxName = Tax::where('name',$name)->where('branch_id',$user->branch_id)->take(1)->value('id');
        if($existTaxName) return ApiResponse::Duplicated('Tax ('.$name.') is already exists.');
        $create = Tax::create($inputs);

        if($create) return ApiResponse::JsonResult(null,false,'Created');

        return ApiResponse::Error('Fail to create');
    }

    public function getTaxes(Request $req){
        $user = UserService::getAuthUser();
        $taxes = Tax::where('company_id',$user->branch_id)->get();
        return ApiResponse::JsonResult($taxes);
    }

    public function getTax(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $tax = Tax::where('company_id',$user->company_id)->find($id);
        return ApiResponse::JsonResult($tax);
    }

    public function update(Request $req,$id=null){
        $validate = $this->taxValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $name = $inputs['name'];
        $id = $id ? $id :$req->id;
        $user = UserService::getAuthUser();
        $tax = Tax::where('company_id',$user->company_id)->find($id);
        if(!$tax) return ApiResponse::NotFound('Tax not found');

        $existsTaxName = Tax::where('name',$req->name)->where('company_id',$user->company_id)->where('id','!=',$id)->take(1)->value('id');
        if($existsTaxName) return ApiResponse::Duplicated('Tax('.$name.') is already taken.');
        // return $user;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $tax->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Update');

        return ApiResponse::Error('Fail to update');
    }
}
