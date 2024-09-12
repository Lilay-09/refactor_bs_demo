<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Services\UserService;
use Illuminate\Http\Request;

class BankController extends Controller
{
    //
    function bankValidation(Request $req){
        return validator($req->all(),[
            'inactive' => 'required|in:true,false',
            'name' => 'required|string|max:30',
            'name_kh' => 'nullable|string|max:30',
            'kh_qr' => 'nullable|string',
            'qr' => 'nullable|string'
        ]);
    }

    public function getBanks(Request $req){
        $user = UserService::getAuthUser();
        $query = Bank::where('company_id',$user->company_id);
        $banks = $query->get();
        return ApiResponse::Pagination($banks);
    }

    public function getBank(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $bank = Bank::where('company_id',$user->company_id)->find($id);
        return ApiResponse::JsonResult($bank);
    }

    public function create(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->bankValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $create = Bank::create($inputs);
        if(!$create) return ApiResponse::Error('Fail to create');
        return ApiResponse::Error('Fail to create');
    }


    public function update(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->bankValidation($req);
        $id = $req->id;
        $bank = Bank::where('company_id',$user->company_id)->find($id);
        if(!$bank) return ApiResponse::NotFound('Bank not found');
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $update = $bank->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to update');
        return ApiResponse::Error('Fail to create');
    }
}
