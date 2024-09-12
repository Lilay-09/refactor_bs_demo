<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use Illuminate\Http\Request;

class BankController extends Controller
{
    //
    function bankValidation(Request $req){
        return validator($req->all(),[
            'inactive' => 'required|in:true,false',

        ]);
    }

    public function create(Request $req){
        $validate = $this->bankValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $create = Bank::create($inputs);
        if(!$create) return ApiResponse::Error('Fail to create');
    }
}
