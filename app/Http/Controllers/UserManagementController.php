<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoles;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    //

    private function userValidation(Request $req){
        return validator($req->all(),[
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'email' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'lock' => 'nullable|in:true,false',
            'role_id' => 'nullable|exists:roles,id'
        ]);
    }


    public function updateUser(Request $req){
        $authUser = UserService::getAuthUser();
        $validate = $this->userValidation($req);
        $id = $req->id;
        $user = User::where('company_id',$authUser->company_id)->find($id);
        if(!$user) return ApiResponse::NotFound('User not found');
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $role_id = $inputs['role_id'] ?? null;
        $update = $user->update([
            'first_name' => $inputs['first_name'],
            'last_name' => $inputs['last_name'],
            'email' => $inputs['email'],
            'user_name' => $inputs['first_name']. '' .$inputs['last_name'],
            'phone' => $inputs['phone'],
        ]);
        if($update){
            if($role_id){
                $userRole = UserRoles::where('user_id',$id);
                if(!$userRole) return ApiResponse::NotFound('Role not found');
                $updateUserRole = $userRole->update([
                    'role_id' => $role_id
                ]);
                if(!$updateUserRole) return ApiResponse::Error('Fail to update role');
            }
            return ApiResponse::JsonResult(null,false,'Updated');
        }
    }

    public function setLockUser(Request $req){
        $authUser = UserService::getAuthUser();
        $id = $req->id;
        $user = User::where('company_id',$authUser->company_id)->find($id);
        if(!$user) return ApiResponse::NotFound('User not found');
        $inputs = [];
        $inputs['update_uid'] = $authUser->id;
        $inputs['branch_id'] = $authUser->branch_id;
        $msg = 'User account has been locked';
        if($user->lock){
            $inputs['lock'] = 0;
            $user->update($inputs);
            $msg = 'User account has been unlocked';
        }else{
            $inputs['lock'] = 1;
            $user->update($inputs);
        }

        return ApiResponse::JsonResult(null,false,$msg);
    }

    function roleValidation(Request $req){
        return validator($req->all(), [
            'name' => 'required|string|max:50',
        ]);
    }

    public function createRole(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->roleValidation($req);
        if($validate->fails()) return ApiResponse::Error($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $exists = Role::where('company_id', $user->company_id)->where('name',$inputs['name'])->first();
        if($exists) return ApiResponse::ValidateFail('Role ('.$inputs['name'].') is already exists');
        $create = Role::create($inputs);
        if(!$create) return ApiResponse::Error('Fail to create role');
        return ApiResponse::JsonResult(null,false,'Created');
    }


    public function updateRole(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $role = Role::where('company_id',$user->company_id)->find($id);
        if(!$role) return ApiResponse::NotFound('Role not found');
        $validate = $this->roleValidation($req);
        if($validate->fails()) return ApiResponse::Error($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $exists = Role::where('company_id', $user->company_id)->where('name',$inputs['name'])->where('id','!=',$id)->first();
        if($exists) return ApiResponse::ValidateFail('Role ('.$inputs['name'].') is already exists');
        $update = $role->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to update role');
        return ApiResponse::JsonResult(null,false,'Updated');
    }

    public function getRoles(Request $req){
        $user = UserService::getAuthUser();
        $roles = Role::where('company_id',$user->company_id)->get();
        return ApiResponse::Pagination($roles,$req);
    }


    public function getRole(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $role = Role::where('company_id',$user->company_id)->find($id);
        return ApiResponse::JsonResult($role);
    }

    public function deleteRole(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $inUse = UserRoles::where('role_id',$id);
        if($inUse) return ApiResponse::ValidateFail('You cannot delete role that has already been used by users.');
        $role = Role::where('company_id',$user->company_id)->find($id);
        if(!$role)return ApiResponse::NotFound('Role does not exist');
        $role->delete();
        return ApiResponse::JsonResult(null,false,'Role deleted');
    }

}
