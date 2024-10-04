<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\UserService;
use Illuminate\Http\Request;

class ExhangeRateController extends Controller
{
    //
    function xRateValidation(Request $req){
        return validator($req->all(),[
            'x_date' => 'required|date',
            'buy_rate' => 'required|numeric',
            'sell_rate' => 'required|numeric',
            'currency_pair' => 'required|in:USD-KHR'
        ]);
    }
    public function create(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->xRateValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $create = ExchangeRate::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function update(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $validate = $this->xRateValidation($req);
        $exchangeRate = ExchangeRate::where('company_id',$user->company_id)->where('void',0)->find($id);
        if(!$exchangeRate) return ApiResponse::NotFound('Exchange rate not found');
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $update = $exchangeRate->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function getXRates(Request $req){
        $user = UserService::getAuthUser();
        $query = ExchangeRate::where('company_id',$user->company_id)->where('void',0);
        $exchangeRates = $query->get();
        return ApiResponse::Pagination($exchangeRates,$req);
    }

    public function getXRate(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $exchangeRate = ExchangeRate::where('company_id',$user->company_id)->where('void',0)->find($id);
        return ApiResponse::JsonResult($exchangeRate);
    }

    public function void(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $exchangeRate = ExchangeRate::where('company_id',$user->company_id)->where('void',0)->find($id);
        if(!$exchangeRate) return ApiResponse::NotFound('Exchange rate not found');
        $exchangeRate->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,false,'Voided');
    }
}
