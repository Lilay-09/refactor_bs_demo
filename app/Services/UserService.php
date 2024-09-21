<?php

namespace App\Services;
use App\Models\BranchSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoles;
use DataResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserService
{
    // Your service methods go here
    public static function getAuthUser($action=''){
        $user = JWTAuth::user();
        if($user){
            $hasUser = User::where('id',$user->id)->first();
            if($hasUser){
                $validActions = ['create','update','modify','void','delete'];
                $roles = UserRoles::where('user_id',$hasUser->id)->with(['role:id,name'])->selectRaw('role_id')->get();
                $hasUser->roles = $roles;
                $action = $action ?? 'void';
                if($action && !in_array($action,$validActions)){
                    return DataResponse::ValidateFail('You action must be one of '.implode(',',$validActions));
                }
                if(!$hasUser->system_admin && $action == 'void'){
                    return DataResponse::Forbidden();
                }
                foreach($roles as $role){
                    $role->id = $role->role->id;
                    $role->name = $role->role->name;
                    unset($role->role);
                }
                return DataResponse::JsonRaw([
                    'error'=>false,
                    'status_code' => 200,
                    'status' => 'OK',
                    'id' => $hasUser->id,
                    'company_id' => $hasUser->company_id,
                    'branch_id' => $hasUser->branch_id,
                    'system_admin' => $hasUser->system_admin,
                    'user'=>$hasUser
                ]);
            }
        }
        return DataResponse::Unauthorized();
    }

    public function userPermissions(){}

    public static function getRolesByUsers($userId){
        return UserRoles::from('user_roles as ur')->where('ur.user_id',$userId)->join('roles as r','r.id','=','ur.role_id')->selectRaw('r.name as role,ur.role_id,r.description')->get();
    }
}
