<?php

namespace App\Services;
use App\Models\BranchSubscription;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoles;
use DataResponse;
use Request;
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
                    'account_type' => $hasUser->account_type,
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


    private static function userValidation(Request $req,$userClass){
        $baseFields = [
            // 'first_name' => 'nullable|string|max:50',
            // 'last_name' => 'nullable|string|max:50',
            'user_name' => 'nullable|max:100',
            'name_km' => 'nullable|max:100',
            'email' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'lock' => 'nullable|in:true,false',
            'gender' => 'nullable|in:M,F,O',
            'branch_id' => 'required|exists:branches,id',
            'role_id' => 'required|exists:roles,id',
            'photo' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:6|max:20'
        ];
        $baseMsgs = [
            'gender.in' => 'Gender must be one of M,F,O'
        ];
        if($userClass == 'admin'){

            return validator($req->all(),$baseFields);
        }else if($userClass == 'driver'){
            $baseFields['employment_date'] = 'nullable|string|max:100';
            $baseFields['shift_type'] = 'nullable|string|max:100';
            $baseFields['vehicle_type'] = 'nullable|string|max:50';
            $baseFields['plate_number'] = 'nullable|string|max:100';
            $baseFields['plate_number'] = 'nullable|string|max:100';
            $baseFields['relative_name'] = 'nullable|string|max:50';
            $baseFields['relative_phone'] = 'nullable|string|max:50';
            $baseFields['relative_relationship'] = 'nullable|string|max:50';
            $baseFields['relative_address'] = 'nullable|string|max:500';
            $baseFields['salary'] = 'nullable|numeric';

            return validator($req->all(),$baseFields);
        }else if($userClass == 'merchant'){
            return validator($req->all(),$baseFields);
        }
    }


    public static function createOrUpdateUser(Request $req,$user_class='admin',$id){

    }
}
