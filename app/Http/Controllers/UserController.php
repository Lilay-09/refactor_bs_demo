<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRoles;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //

    public function getUsers(Request $req){
        $user = UserService::getAuthUser();
        $userList = User::where('company_id',$user->company_id)->selectRaw('user_name,phone,email,phone,system_admin,lock,id,last_login,start_date,branch_id')->get();
        return ApiResponse::Pagination($userList, $req);
    }

    public function getUser(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $user = User::where('company_id',$user->company_id)->selectRaw('user_name,first_name,last_name,phone,email,phone,system_admin,lock,id,last_login,start_date,branch_id')->find($id);
        if($user){
            $user->role_id = UserRoles::where('user_id',$id)->take(1)->value('role_id');
        }
        return ApiResponse::JsonResult($user);
    }
}
