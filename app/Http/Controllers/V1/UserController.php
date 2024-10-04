<?php

namespace App\Http\Controllers\V1;

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
        $role = $req->role;
        $branch = $req->branch;
        $search = $req->search;
        $query = User::where('company_id',$user->company_id)->with(['user_roles'])->selectRaw('user_name,phone,email,phone,system_admin,lock,id,last_login,start_date,branch_id,photo_file_name')->orderByDesc('id');
        if($role){
            $roleArr = explode(',',$role);
            $query->whereHas('user_roles',function($query) use($roleArr){
                $query->whereIn('role_id',$roleArr);
            });
        }
        if($search){
            $query->where('first_name','ilike','%'.$search.'%')
            ->orWhere('last_name','ilike','%'.$search.'%')
            ->orWhere('user_name','ilike','%'.$search.'%')
            ->orWhere('phone','ilike','%'.$search.'%');
        }
        if($branch){
            $branchArr = explode(',',$branch);
            $query->whereIn('branch_id',$branchArr);
        }
        $userList = $query->get();
        foreach($userList as $u){
            $u->image_url = Helper::getImageUrl($u->photo_file_name,$user->company_id,$this->userProfileDir);
        }
        return ApiResponse::Pagination($userList, $req);
    }


    public function getProfile(){
        $authUser = UserService::getAuthUser();
        $id = $authUser->id;
        $user = User::where('company_id',$authUser->company_id)->selectRaw('user_name,first_name,last_name,phone,email,phone,branch_id,photo_file_name')->find($id);
        if($user){
            $user->role_id = UserRoles::where('user_id',$id)->take(1)->value('role_id');
            $user->image_url = Helper::getImageUrl($user->photo_file_name,$authUser->company_id,$this->userProfileDir);
        }
        return ApiResponse::JsonResult($user);
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
