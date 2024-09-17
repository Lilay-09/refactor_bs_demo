<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRoles;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //
    protected $userProfileDir = 'user_profile';

    public function getUsers(Request $req){
        $user = UserService::getAuthUser();
        $userList = User::where('company_id',$user->company_id)->selectRaw('user_name,phone,email,phone,system_admin,lock,id,last_login,start_date,branch_id,photo_file_name')->get();
        foreach($userList as $u){
            $u->image_url = Helper::getImageUrl($u->photo_file_name,$user->company_id,$this->userProfileDir);
        }
        return ApiResponse::Pagination($userList, $req);
    }

    public function getUser(Request $req){
        $authUser = UserService::getAuthUser();
        $id = $req->id;
        $user = User::where('company_id',$authUser->company_id)->selectRaw('user_name,first_name,last_name,phone,email,phone,system_admin,lock,id,last_login,start_date,branch_id,photo_file_name')->find($id);
        if($user){
            $user->role_id = UserRoles::where('user_id',$id)->take(1)->value('role_id');
            $user->image_url = Helper::getImageUrl($user->photo_file_name,$authUser->company_id,$this->userProfileDir);
        }
        return ApiResponse::JsonResult($user);
    }
}
