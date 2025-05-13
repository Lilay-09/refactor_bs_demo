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
            'merchant_id' => 'required|int',
			'address' => 'string|nullable|max:250'
		]);
    }
    // Your service methods go here
    public function createMerchantEmployee(Request $req,$user){
        $validate = $this->MerchantEmployeeValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        MerchantEmployee::create($inputs);
        return DataResponse::JsonResult(null,false,"Created");
    }

    public function updateMerchantEmployee (Request $req,int $id,$user){
        $MerchantEmployee = MerchantEmployee::where('is_deleted',0)->find($id);
        if(!$MerchantEmployee) return DataResponse::NotFound("MerchantEmployee not found");
        $validate = $this->MerchantEmployeeValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $MerchantEmployee->update($inputs);
        return DataResponse::JsonResult(null,false,"Updated");
    }

    public function getMerchantEmployees (Request $req,$user){
        $MerchantEmployee = MerchantEmployee::query()->where('is_deleted',0);
        return DataResponse::PaginationV1($MerchantEmployee,$req);
    }

    public function getOneMerchantEmployee(int $id,$user){
        $MerchantEmployee = MerchantEmployee::where('is_deleted',0)->find($id);
        if(!$MerchantEmployee) return DataResponse::NotFound("MerchantEmployee not found");
        return DataResponse::JsonResult($MerchantEmployee,false,"get one MerchantEmployee");
    }

    public function deleteMerchantEmployee(int $id,$user){
        $MerchantEmployee = MerchantEmployee::where('is_deleted',0)->find($id);
        if(!$MerchantEmployee) return DataResponse::NotFound("MerchantEmployee not found");
        $MerchantEmployee->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,false,"Deleted");
    }
}
