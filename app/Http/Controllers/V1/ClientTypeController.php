<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ClientType;
use App\Services\UserService;
use Illuminate\Http\Request;

class ClientTypeController extends Controller
{
    //
    public function saveClientType(Request $req){
        $user = UserService::getAuthUser();
        $req->merge([
            'create_uid' => $user->id,
            'update_uid' => $user->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ]);
        ClientType::upsert($req->all(),['id'],['id','name']);
        return ApiResponse::JsonResult(null,'Saved');
    }


    public function getClientTypes(){
        return ClientType::select(['id','name','discount_percent'])->get();
    }
}
