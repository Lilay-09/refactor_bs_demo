<?php

namespace App\Services;
use App\Models\BranchSubscription;
use App\Models\User;
use App\Models\UserRoles;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserService
{
    // Your service methods go here
    public static function getAuthUser(){
        $user = JWTAuth::user();
        if($user){
            $hasUser = User::where('id',$user->id)->first();
            if($hasUser){
                return $hasUser;
            }
        }
        return null;
    }

    public static function getRolesByUsers($userId){
        return UserRoles::from('user_roles as ur')->where('ur.user_id',$userId)->join('roles as r','r.id','=','ur.role_id')->selectRaw('r.name as role,ur.role_id,r.description')->get();
    }
}
