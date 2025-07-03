<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppModule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserModule;
use App\Models\UserPermission;
use App\Models\UserRoles;
use App\Services\UserManagementService;
use App\Services\UserService;
use DB;
use Helper;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    //
    protected $userProfileDir = 'user_profile';

    public function getUsers(Request $req){
        $user = UserService::getAuthUser();
        $role = $req->role;
        $roleId = $req->role_id;
        // $branchId = $req->branch_id;
        $search = $req->search;
        $query = User::where('is_deleted',0)->with(['user_roles.role'])
        ->selectRaw('id,code,username,phone,email,account_type,phone,system_admin,lock,last_login,registered_datetime,branch_id,photo_file_name,login_name,created_at')->orderByDesc('id');
        if($role){
            $roleArr = explode(',',$role);
            $query->whereHas('user_roles',function($query) use($roleArr){
                $query->whereIn('role_id',$roleArr);
            });
        }
        if($roleId){
            $query->whereHas('user_roles',function($query) use($roleId){
                $query->where('role_id',$roleId);
            });
        }
        if($search){
            $query->where('username','ilike','%'.$search.'%')
            ->orWhere('phone','ilike','%'.$search.'%');
        }
        // if($branchId){
        //     $branchArr = explode(',',$branchId);
        //     $query->whereIn('branch_id',$branchArr);
        // }
        $userList = $query;
        $callback = function ($u) use($user){
            $u->create_date = Helper::formatCustomDateTime($u->created_at);
            $u->role = $u->user_roles[0]?->role?->name ?? null;
            $u->login_name = $u->login_name ?? $u->phone;
            $u->last_login = Helper::formatCustomDateTime($u->last_login);
            $u->image_url = Helper::getImageUrl($u->photo_file_name,$user->company_id,$this->userProfileDir);
            unset($u->user_roles,$u->created_date);
            return $u;
        };
        return ApiResponse::PaginationV1($userList, $req,'',[],200,$callback);
    }

    public function getOneUser(Request $req){
        $id = $req->id;
        $user = User::with(['user_roles.role'])
        ->selectRaw('id,code,username,phone,email,account_type,phone,system_admin,lock,last_login,registered_datetime,branch_id,photo_file_name,login_name,created_at,company_id,dob')
        ->orderByDesc('id')->find($id);
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        $user->role_id = $user->user_roles[0]?->role_id ?? null;
        $user->image_url = Helper::getImageUrl($user->photo_file_name,$user->company_id,$this->userProfileDir);
        unset($user->user_roles);
        return ApiResponse::JsonResult($user,'get one user');
    }

    public function getApplications() {
        return Application::selectRaw('id,app_type,name,is_mobile_app,user_class')
                ->get();
    }

    public function logout(Request $req){
        $authUser = UserService::getAuthUser();
        $createUser = UserService::logOut($req,$authUser);
        return ApiResponse::flex($createUser);
    }


    public function createApplication(Request $req){
        // Insert the application with binary data
        DB::table('applications')->insert([
            'id' => $req->code,  // Insert binary data into the primary key column
            'name' => $req->name,
            'app_type' => $req->app_type ?? 'admin-panel',
            'is_mobile_app' => $req->is_mobile_app ?? false
        ]);
    }

    public function saveApplication(Request $req){
        $id = $req->id;
        $app = Application::whereRaw('id = ?', [$id])->first();
        if(!$app) return;
        $app->update([
            'name' => $req->name,
            'is_mobile_app' => $req->is_mobile_app ?? false,
            'app_type' => $req->app_type ?? 'admin-panel'
        ]);
    }

    private function userValidation(Request $req){
        return validator($req->all(),[
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'email' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'lock' => 'nullable|in:true,false',
            'login_name' => 'required|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
            'role_id' => 'required|exists:roles,id',
            'photo' => 'nullable|string',
            'password' => 'required|string|min:6|max:20',
            'confirm_password' => 'required|string|min:6|max:20'
        ]);
    }

    public function createUser(Request $req){
        $authUser = UserService::getAuthUser();
        $createUser = UserService::createOrUpdateUser($req,'admin',$authUser);
        return ApiResponse::flex($createUser);
    }


    public function updateUser(Request $req){
        $authUser = UserService::getAuthUser();
        $id = $req->id;
        $updateUser = UserService::createOrUpdateUser($req,'admin',$authUser,$id);
        return ApiResponse::flex($updateUser);
    }

    public function changeLoginName(Request $req){
        $authUser = UserService::getAuthUser();
        $updateUser = UserService::changeLoginName($req->id,$req->login_name,$authUser);
        return ApiResponse::flex($updateUser);
    }

    public function getUserRole(Request $req){
        $roles = Role::selectRaw('name_en as name,id')->get();
        $userId = $req->id;
        $user = User::where('id',$userId)->selectRaw('system_admin,account_type')->first();
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        if($user->account_type != 'admin') return ApiResponse::JsonResult(null,'no roles available');
        $userRoles = UserRoles::where('user_id',$userId)->get();
        foreach($roles as $p){
            $p->accessing = $this->getAccessOrDenied($userRoles,$p->id,$user->system_admin,'role');
        }
        return ApiResponse::JsonResult($roles);
    }

    public function getAccessability(Request $req){
        $user = UserService::getAuthUser();
        $userId = $user->id;
        $permissionIds = UserPermission::where('user_id',$userId)->pluck('permission_id')->toArray();
        $moduleIds = UserModule::join('app_modules as am','am.id','user_app_modules.module_id')->where('user_app_modules.user_id',$userId)->orderBy('am.display_order')->pluck('user_app_modules.module_id')->toArray();
        return ApiResponse::JsonResult([
            'modules' => $moduleIds,
            'permissions' => $permissionIds
        ]);
    }

    public function getUserPermissions(Request $req){
        $permissions = Permission::from('permissions as p')->join('app_modules as m','m.id','p.module_id')->orderBy('m.display_order')->selectRaw('p.id,p.name as permission_name,m.native_name as module_name')->get();
        $userId = $req->id;
        $user = User::where('id',$userId)->selectRaw('system_admin,account_type')->first();
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        if($user->account_type != 'admin') return ApiResponse::JsonResult(null,'no permissions available');
        $userPermissions = UserPermission::where('user_id',$userId)->get();
        foreach($permissions as $p){
            $p->permission_name = $p->permission_name. ' ('.$p->id.')';
            $p->accessing = $this->getAccessOrDenied($userPermissions,$p->id,$user->system_admin);
        }
        return ApiResponse::JsonResult($permissions);
    }

    public function getPermissions(){
        $permissions = Permission::selectRaw('id,name')->get();
        return ApiResponse::JsonResult($permissions);
    }

    public function setHiddenModule(Request $req){
        AppModule::where('id',$req->id)->update([
            'hidden' => $req->hidden,
        ]);
        return ApiResponse::JsonResult(null);
    }

    public function getUserModules(Request $req){
        $userId = $req->id;
        $user = User::where('is_deleted',0)->where('id',$userId)->selectRaw('system_admin,account_type')->first();
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' =>'User'
        ]));
        if($user->account_type != 'admin') return ApiResponse::JsonResult(null,'no modules available');
        $appModules = AppModule::where('hidden',0)->orderBy('display_order')->selectRaw('id,native_name as name')->get();
        $userModules = UserModule::where('user_id',$userId)->get();
        foreach($appModules as $m){
            $m->accessing = $this->getAccessOrDenied($userModules,$m->id,$user->system_admin,'module');
        }
        return ApiResponse::JsonResult($appModules);
    }

    private function getAccessOrDenied($rows,$id,$isSystemAdmin=false,$type='permission'){
        foreach($rows as $r){
            if($r->{$type.'_id'} == $id){
                return [
                    'allowed' => 1,
                    'title' => 'Allowed'
                ];
            }
        }
        if($isSystemAdmin && $type != 'role') return [
            'allowed' => 1,
            'title' => 'Allowed'
        ];
        return [
            'allowed' => 0,
            'title' => 'Denied'
        ];
    }

    public function assignUserPermission(Request $req){
        $userId = $req->id;
        $permissionId = $req->permission_id;
        $user = User::where('is_deleted',0)->find($userId);
        $permission = Permission::where('is_deleted',0)->find($permissionId);
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        if($user->account_type != 'admin') return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only admin users can be assigned'
        ]));
        if(!$permission) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'permission'
        ]));
        $exists = UserPermission::where('user_id',$userId)->where('permission_id',$permissionId)->first();
        if($exists) return ApiResponse::Duplicated('User already has this permission');
        UserPermission::create([
            'user_id' => $userId,
            'permission_id' => $permissionId
        ]);
        $existModule = UserModule::where('user_id',$userId)->where('module_id',$permission->module_id)->first();
        if(!$existModule) UserModule::create([
            'user_id' => $userId,
            'module_id' => $permission->module_id
        ]);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Permission has added'
        ]));
    }

    public function removeUserPermission(Request $req){
        $userId = $req->id;
        $permissionId = $req->permission_id;
        $user = User::where('is_deleted',0)->find($userId);
        $module = Permission::where('is_deleted',0)->find($permissionId);
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        if($user->system_admin) return ApiResponse::Forbidden(__('messages.info',[
            'info' => 'You can remove permission from system admin'
        ]));
        if($user->account_type != 'admin') return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only admin users can be assigned'
        ]));
        if(!$module) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Permission'
        ]));
        UserPermission::where('user_id',$userId)->where('permission_id',$permissionId)->delete();
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Module has removed from user'
        ]));
    }

    public function assignUserModule(Request $req){
        $userId = $req->id;
        $moduleId = $req->module_id;
        $user = User::where('is_deleted',0)->find($userId);
        $module = AppModule::where('is_deleted',0)->find($moduleId);
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        if($user->account_type != 'admin') return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Only admin users can be assigned'
        ]));
        if(!$module) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Module'
        ]));
        $exists = UserModule::where('user_id',$userId)->where('module_id',$moduleId)->first();
        if($exists) return ApiResponse::Duplicated('User already has this module');
        UserModule::create([
            'user_id' => $userId,
            'module_id' => $moduleId
        ]);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Module has added'
        ]));
    }

    public function removeUserModule(Request $req){
        $userId = $req->id;
        $moduleId = $req->module_id;
        $user = User::where('is_deleted',0)->find($userId);
        $module = AppModule::where('is_deleted',0)->find($moduleId);
        if(!$user) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        if($user->system_admin) return ApiResponse::Forbidden(__('messages.info',[
            'info' => 'You cannot remove module from system admin'
        ]));
        if(!$module) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Module'
        ]));
        UserModule::where('user_id',$userId)->where('module_id',$moduleId)->delete();
        $relatedPermissionIds = Permission::where('module_id',$moduleId)->pluck('id')->toArray();
        UserPermission::where('user_id',$userId)->whereIn('permission_id',$relatedPermissionIds)->delete();
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Module has removed from user'
        ]));
    }

    public function deleteUser(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::deleteUser($req->id,'all',$user));
    }

    public function userChangePassword(Request $req){
        $authUser = UserService::getAuthUser();
        $setPwd = UserService::setNewPassword($req,$req->id,'admin',$authUser);
        return ApiResponse::flex($setPwd);
    }

    public function setLockUser(Request $req){
        $authUser = UserService::getAuthUser();
        $setLock = UserService::setLockUser($authUser,$req->id);
        return ApiResponse::flex($setLock);
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
        return ApiResponse::JsonResult(null,'Created');
    }

    public function assignPermissionRoles(Request $req){
        $user = UserService::getAuthUser();
        $roleId = $req->id;
        $permissionIds = $req->permissions;
        $role = Role::where()->find($roleId);
    }


    public function updateRole(Request $req){
        $id = $req->id;
        if(in_array($id,[1,2,3])) return ApiResponse::ValidateFail('You can not update the default role');
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
        return ApiResponse::JsonResult(null,'Updated');
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
        // $role->delete();
        return ApiResponse::JsonResult(null,'Role deleted');
    }

    public function getModules(Request $req){
        $search = $req->search;
        $qM = AppModule::where('is_deleted',0);
        if($search){
            $qM->where('name','ilike','%'.$search.'%');
        }
        $modules = $qM->get();
        return ApiResponse::JsonResult($modules);
    }

    public function saveModule(Request $req){
        $user = UserService::getAuthUser();
        $um = new UserManagementService();
        return ApiResponse::JsonResult($um->saveModule($req,$user));
    }

}
