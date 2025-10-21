<?php

namespace App\Services;

use App\Models\AppModule;
use App\Models\User;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserManagementService
{
    // Your service methods go here
    public function saveModule(Request $req,$user){
        $id = $req->id;
        DB::beginTransaction();
        try{
            $msg = null;
            if($id) {
                $update = $this->updateModule($req,$user);
                if($update->error) return $update;
                $msg = $update->message;
            }else {
                $create = $this->createModule($req,$user);
                if($create->error) return $create;
                $msg = $create->message;
            }
            DB::commit();
            return DataResponse::JsonResult(null,$msg);
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return DataResponse::Error('Failed');
        }

    }


    private function appModuleValidation(Request $req){
        return validator($req->all(),[
            'id' => 'nullable|int',
            'assign_id' => 'nullable|int',
            'name' => 'nullable|string',
            'name_kh' => 'nullable|string',
            'native_name' => 'nullable|string',
            'native_name_kh' => 'nullable|string',
            'hidden' => 'nullable|in:0,1',
            'display_order' => 'int'
        ]);
    }

    private function createModule(Request $req,$user){
        $validate = $this->appModuleValidation($req);
        if ($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $assignId = $inputs['assign_id'] ?? null;
        if($assignId) $inputs['id'] = $assignId;
        $nativeName = $inputs['native_name'] ?? null;
        $exName = AppModule::where('native_name',$nativeName)->value('id');
        if($exName) return DataResponse::Duplicated('Duplicated name');
        $create = AppModule::create($inputs);
        if(!$create) return DataResponse::Error('Failed');
        return DataResponse::JsonResult(null,false,'Created => '.$create->id);
    }
    private function updateModule(Request $req,$user){
        $module = AppModule::where('id',$req->id)->first();
        if(!$module) return DataResponse::NotFound('Module not found');
        $validate = $this->appModuleValidation($req);
        if ($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $assignId = $inputs['assign_id'] ?? null;
        $nativeName = $inputs['native_name'] ?? null;
        if($assignId) $inputs['id'] = $assignId;
        $exName = AppModule::where('id','!=',$req->id)->where('native_name',$nativeName)->value('id');
        if($exName) return DataResponse::Duplicated('Duplicated name');
        $update = $module->update($inputs);
        if(!$update) return DataResponse::Error('Failed');
        return DataResponse::JsonResult(null,false,'Updated');
    }




    public function savePermission(Request $req,$user){
        $id = $req->id;
        DB::beginTransaction();
        try{
            $msg = null;
            if($id) {
                $update = $this->updatePermission($req,$user);
                if($update->error) return $update;
                $msg = $update->message;
            }else {
                $create = $this->createPermission($req,$user);
                if($create->error) return $create;
                $msg = $create->message;
            }
            DB::commit();
            return DataResponse::JsonResult(null,$msg);
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getTraceAsString());
            Log::error($e->getMessage());
            return DataResponse::Error('Failed');
        }

    }


    private function permissionValidation(Request $req){
        return validator($req->all(),[
            'id' => 'nullable|int',
            'assign_id' => 'nullable|int',
            "module_id" => 'nullable|int',
            'name' => 'nullable|string',
        ]);
    }

    private function createPermission(Request $req,$user){
        $validate = $this->permissionValidation($req);
        if ($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $assignId = $inputs['assign_id'] ?? null;
        if($assignId) $inputs['id'] = $assignId;
        $name = $inputs['name'] ?? null;
        $exName = AppModule::where('name',$name)->value('id');
        if($exName) return DataResponse::Duplicated('Duplicated name');
        $create = AppModule::create($inputs);
        if(!$create) return DataResponse::Error('Failed');
        return DataResponse::JsonResult(null,false,'Created => '.$create->id);
    }
    private function updatePermission(Request $req,$user){
        $module = AppModule::where('id',$req->id)->first();
        if(!$module) return DataResponse::NotFound('Module not found');
        $validate = $this->appModuleValidation($req);
        if ($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $assignId = $inputs['assign_id'] ?? null;
        $name = $inputs['name'] ?? null;
        if($assignId) $inputs['id'] = $assignId;
        $exName = AppModule::where('id','!=',$req->id)->where('native_name',$name)->value('id');
        if($exName) return DataResponse::Duplicated('Duplicated name');
        $update = $module->update($inputs);
        if(!$update) return DataResponse::Error('Failed');
        return DataResponse::JsonResult(null,false,'Updated');
    }

    public static function setLockBatchUsers(string $isLock,string $isAll='0', array $driverIds,$type='driver'){
        $toLockUsers = User::where('account_type',$type);
        if($isAll !== '1'){
            $toLockUsers->whereIn('id',$driverIds);
        }
        $toLockUsers->update([
            'lock' => $isLock
        ]);
        $message = $isLock == '0' ? 'Active drivers': 'Locked Drivers';
        return DataResponse::JsonResult(null,false,$message);
    }
}
