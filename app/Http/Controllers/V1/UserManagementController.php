<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppModule;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoles;
use App\Services\UserManagementService;
use App\Services\UserService;
use DataResponse;
use DB;
use Exception;
use Hash;
use Helper;
use Illuminate\Http\Request;
use Log;

class UserManagementController extends Controller
{
    //
    protected $userProfileDir = 'user_profile';

    public function getUsers(Request $req){
        $user = UserService::getAuthUser();
        $role = $req->role;
        // $branchId = $req->branch_id;
        $search = $req->search;
        $query = User::where('company_id',$user->company_id)->with(['user_roles'])->selectRaw('id,app_id,user_name,phone,email,phone,system_admin,lock,last_login,registered_datetime,branch_id,photo_file_name')->orderByDesc('id');
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
        // if($branchId){
        //     $branchArr = explode(',',$branchId);
        //     $query->whereIn('branch_id',$branchArr);
        // }
        $userList = $query->get();
        foreach($userList as $u){
            $u->image_url = Helper::getImageUrl($u->photo_file_name,$user->company_id,$this->userProfileDir);
        }
        return ApiResponse::Pagination($userList, $req);
    }

    public function getApplications() {
        return Application::selectRaw('id,app_type,name,is_mobile_app,user_class')
                        ->get();
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
            'phone' => 'nullable|string|max:20',
            'lock' => 'nullable|in:true,false',
            'branch_id' => 'required|exists:branches,id',
            'role_id' => 'required|exists:roles,id',
            'photo' => 'nullable|string',
            'password' => 'nullable|string|min:6|max:20'
        ]);
    }

    public function createUser(Request $req){
        $authUser = UserService::getAuthUser();
        $validate = $this->userValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $role_id = $inputs['role_id'] ?? null;
        $photo = $inputs['photo'] ?? null;
        $hPwd = Hash::make($inputs['password']);
        $createArr = [
            'last_login' => null,
            'create_uid' => $authUser->id,
            'update_uid' => $authUser->id,
            'branch_id' => $inputs['branch_id'] ?? $authUser->branch_id,
            'company_id' => $authUser->company_id,
            'first_name' => $inputs['first_name'],
            'last_name' => $inputs['last_name'],
            'email' => $inputs['email'],
            'user_name' => $inputs['first_name']. ' ' .$inputs['last_name'],
            'phone' => $inputs['phone'],
            'password' => $hPwd
        ];

        if(Helper::isValidBase64Image($photo)){
            $photo_file = Helper::base64ToImageFile($photo,$authUser->company_id,'user_profile')->filename;
            $createArr['photo_file_name'] = $photo_file;
        }

        $createUser = User::create($createArr);
        if(!$createUser){
            Helper::deleteImageFile($createArr['photo_file_name'],$authUser->company_id,'user_profile');
            return ApiResponse::Error('Fail to create user');
        }
        if($role_id){
            UserRoles::create([
                'user_id' => $createUser->id,
                'role_id' => $role_id
            ]);
        }
        return ApiResponse::JsonResult(null,'Created');
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
        $photo = $inputs['photo'] ?? null;

        $updateArr = [
            'first_name' => $inputs['first_name'],
            'last_name' => $inputs['last_name'],
            'email' => $inputs['email'],
            'user_name' => $inputs['first_name']. '' .$inputs['last_name'],
            'phone' => $inputs['phone'],
            'branch_id' => $inputs['branch_id']
        ];
        $createdAt = $user->created_at ?? null;
        if($createdAt && !$user->start_date){
            $updateArr['start_date'] = $createdAt;
            // return ApiResponse::ValidateFail('fail now'.$user->created_at);
        }
        if(!$photo || Helper::isValidBase64Image($photo)){
            $photo_file = Helper::base64ToImageFile($photo,$authUser->company_id,'user_profile')->filename;
            $updateArr['photo_file_name'] = $photo_file;
                //** delete exists photo */
            Helper::deleteImageFile($user->photo_file_name,$authUser->company_id,'user_profile');
        }
        $update = $user->update($updateArr);
        if($update){
            if($role_id){
                $userRole = UserRoles::where('user_id',$id)->first();
                if(!$userRole) {
                    $updateUserRole = UserRoles::create([
                        'user_id' => $id,
                        'role_id' => $role_id
                    ]);
                    if(!$updateUserRole) return ApiResponse::Error('Fail to update role');
                }else{
                    $updateUserRole = $userRole->update([
                        'role_id' => $role_id,
                        'user_id' => $id
                    ]);
                    if(!$updateUserRole) return ApiResponse::Error('Fail to update role');
                }
            }
            return ApiResponse::JsonResult(null,'Updated');
        }
    }

    public function userChangePassword(Request $req){
        $authUser = UserService::getAuthUser();
        $id = $req->id;
        $validate = validator($req->all(),[
            'new_password' => 'required|string|min:6|max:20'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $new_password = $inputs['new_password'];
        $user = User::where('company_id',$authUser->company_id)->find($id);
        if(!$user) return ApiResponse::NotFound('User not found');
        $newHash = Hash::make($new_password);
        $update = $user->update([
            'udpate_uid' => $authUser->id,
            'company_id' => $authUser->company_id,
            'branch_id' => $authUser->branch_id,
            'password' => $newHash
        ]);

        if(!$update) return ApiResponse::Error('fail to change password');
        return ApiResponse::JsonResult(null,'Password has been changed');

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
        return ApiResponse::JsonResult(null,$msg);
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
