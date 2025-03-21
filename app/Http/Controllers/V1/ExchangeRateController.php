<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\UserService;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
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
        if($create) return ApiResponse::JsonResult(null,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function update(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $validate = $this->xRateValidation($req);
        $exchangeRate = ExchangeRate::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$exchangeRate) return ApiResponse::NotFound(__('messages.not_found'));
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $update = $exchangeRate->update($inputs);
        if($update) return ApiResponse::JsonResult(null,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function getXRates(Request $req){
        $user = UserService::getAuthUser();
        $query = ExchangeRate::where('company_id',$user->company_id)->where('is_deleted',0);
        $exchangeRates = $query->get();
        return ApiResponse::Pagination($exchangeRates,$req);
    }

    public function getXRate(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $exchangeRate = ExchangeRate::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        return ApiResponse::JsonResult($exchangeRate);
    }

    public function delete(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $exchangeRate = ExchangeRate::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$exchangeRate) return ApiResponse::NotFound(__('messages.not_found'));
        $exchangeRate->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,'Deleted');
    }
}
