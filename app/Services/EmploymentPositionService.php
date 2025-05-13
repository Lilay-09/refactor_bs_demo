<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\EmploymentPosition;
use DataResponse;

class EmploymentPositionService
{
    private function EmploymentPositionValidation(Request $req){
        return validator($req->all(),[
			'name_en' => 'required|string|max:35',
			'name_km' => 'nullable|string|max:35',
			'descrioption_en' => 'nullable|string|max:500'
		]);
    }
    // Your service methods go here
    public function createEmploymentPosition(Request $req,$user){
        $validate = $this->EmploymentPositionValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        EmploymentPosition::create($inputs);
        return DataResponse::JsonResult(null,false,"Created");
    }

    public function updateEmploymentPosition (Request $req,int $id,$user){
        $EmploymentPosition = EmploymentPosition::where('is_deleted',0)->find($id);
        if(!$EmploymentPosition) return DataResponse::NotFound("EmploymentPosition not found");
        $validate = $this->EmploymentPositionValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $EmploymentPosition->update($inputs);
        return DataResponse::JsonResult(null,false,"Updated");
    }

    public function getEmploymentPositions(Request $req,$user){
        $EmploymentPosition = EmploymentPosition::query()->where('is_deleted',0);
        return DataResponse::PaginationV1($EmploymentPosition,$req);
    }

    public function getOneEmploymentPosition(int $id,$user){
        $EmploymentPosition = EmploymentPosition::where('is_deleted',0)->find($id);
        if(!$EmploymentPosition) return DataResponse::NotFound("EmploymentPosition not found");
        return DataResponse::JsonResult($EmploymentPosition,false,"get one EmploymentPosition");
    }

    public function deleteEmploymentPosition(int $id,$user){
        $EmploymentPosition = EmploymentPosition::where('is_deleted',0)->find($id);
        if(!$EmploymentPosition) return DataResponse::NotFound("EmploymentPosition not found");
        $EmploymentPosition->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,false,"Deleted");
    }
}
