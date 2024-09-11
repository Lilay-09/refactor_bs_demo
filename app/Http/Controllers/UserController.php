<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //

    public function getUsers(Request $req){
        $user = UserService::getAuthUser();
        $userList = User::where('company_id',$user->company_id)->selectRaw('user_name,phone,email,phone,system_admin,lock,id,last_login,start_date')->get();
        return ApiResponse::Pagination($userList, $req);
    }
}
