<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\MerchantEmployee;
use DataResponse;

class MerchantEmployeeService
{
    private function MerchantEmployeeValidation(Request $req){
        return validator($req->all(),[
			'name' => 'required|string|max:50',
			'phone' => 'required|string|min:9',
			'gender' => 'nullable|string',
			'address' => 'string|nullable|max:250'
		]);
    }
    // Your service methods go here
    public function createMerchantEmployee(Request $req,int $merchantId,$user){
        $validate = $this->MerchantEmployeeValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['merchant_id'] = $merchantId;
        $existPhone = $this->checkPhoneExists($inputs['phone']);
        if($existPhone) {
            return DataResponse::ValidateFail("Phone already exists");
        }
        MerchantEmployee::create($inputs);
        return DataResponse::JsonResult(null,false,"Created");
    }

    private function checkPhoneExists($phone,$id=null){
        $query = MerchantEmployee::where('is_deleted',0)->where('phone',$phone);
        if($id) $query->where('id','!=',$id);
        return $query->exists();
    }

    public function updateMerchantEmployee (Request $req,int $id,int $merchantId,$user){
        $MerchantEmployee = MerchantEmployee::where('is_deleted',0)->where('merchant_id',$merchantId)->find($id);
        if(!$MerchantEmployee) return DataResponse::NotFound("MerchantEmployee not found");
        $validate = $this->MerchantEmployeeValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $existPhone = $this->checkPhoneExists($inputs['phone'],$id);
        if($existPhone) {
            return DataResponse::ValidateFail("Phone already exists");
        }
        $MerchantEmployee->update($inputs);
        return DataResponse::JsonResult(null,false,"Updated");
    }

    public function getMerchantEmployees (Request $req,int $merchantId,$user){
        $MerchantEmployee = MerchantEmployee::query()->where('merchant_id',$merchantId)
        ->where('is_deleted',0);
        return DataResponse::PaginationV1($MerchantEmployee,$req);
    }

    public function getOneMerchantEmployee(int $id,int $merchantId,$user){
        $MerchantEmployee = MerchantEmployee::where('is_deleted',0)
        ->where('merchant_id',$merchantId)
        ->find($id);
        if(!$MerchantEmployee) return DataResponse::NotFound("MerchantEmployee not found");
        return DataResponse::JsonResult($MerchantEmployee,false,"get one MerchantEmployee");
    }

    public function deleteMerchantEmployee(int $id,int $merchantId,$user){
        $MerchantEmployee = MerchantEmployee::where('is_deleted',0)->where('merchant_id',$merchantId)->find($id);
        if(!$MerchantEmployee) return DataResponse::NotFound("MerchantEmployee not found");
        $MerchantEmployee->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,false,"Deleted");
    }
}
