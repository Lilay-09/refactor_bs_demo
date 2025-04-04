<?php

namespace App\Http\Controllers\V1;
use Helper;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Models\EmergencyContact;
use ApiResponse;

class EmergencyContactController
{
    public function EmergencyContactValidation(Request $req){
        return validator($req->all(),[
			'name_en' => 'required|string|max:50',
			'name_km' => 'nullable|string',
			'logo' => 'nullable|string',
			'phone' => 'required|string'
		]);
    }
    // Your service methods go here
    public function createEmergencyContact(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->EmergencyContactValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $logo = $inputs['logo'] ?? null;
        unset($inputs['logo']);
        if($logo){
            $image = Helper::base64ToImageFile($logo,$user->company_id,'emergency');
            $inputs['logo'] = $image->filename;
        }
        EmergencyContact::create($inputs);
        return ApiResponse::JsonResult(null,"Created");
    }

    public function updateEmergencyContact (Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $EmergencyContact = EmergencyContact::where('is_deleted',0)->find($id);
        if(!$EmergencyContact) return ApiResponse::NotFound("$EmergencyContact not found");
        $validate = $this->EmergencyContactValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $logo = $inputs['logo'] ?? null;
        unset($inputs['logo']);
        if(Helper::isValidBase64Image($logo) || !$logo){
            Helper::deleteImageFile($EmergencyContact->logo,$user->company_id,'emergency');
            $image = Helper::base64ToImageFile($logo,$user->company_id,'emergency');
            $inputs['logo'] = $image->filename;
        }
        $EmergencyContact->update($inputs);
        return ApiResponse::JsonResult(null,"Updated");
    }

    public function getEmergencyContacts(Request $req){
        $user = UserService::getAuthUser();
        $EmergencyContact = EmergencyContact::query()
        ->select('id','logo','name_km','name_en','phone')
        ->where('is_deleted',0);
        $callback = function ($emergencyContact) use($user){
            $emergencyContact->logo = Helper::getImageUrl($emergencyContact->logo,$user->company_id,'emergency');
            return $emergencyContact;
        };
        return ApiResponse::PaginationV1($EmergencyContact,$req,'',[],20,$callback);
    }

    public function getOneEmergencyContact(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $EmergencyContact = EmergencyContact::where('is_deleted',0)
        ->select('id','logo','name_km','name_en','phone')
        ->find($id);
        if(!$EmergencyContact) return ApiResponse::NotFound("$EmergencyContact not found");
        $EmergencyContact->logo = Helper::getImageUrl($EmergencyContact->logo,$user->company_id,'emergency');
        return ApiResponse::JsonResult($EmergencyContact,"get one");
    }

    public function deleteEmergencyContact(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $EmergencyContact = EmergencyContact::where('is_deleted',0)->find($id);
        if(!$EmergencyContact) return ApiResponse::NotFound("not found");
        $EmergencyContact->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return ApiResponse::JsonResult(null,"Deleted");
    }
}
