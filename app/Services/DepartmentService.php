<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\Department;
use DataResponse;

class DepartmentService
{
    private function DepartmentValidation(Request $req){
        return validator($req->all(),[
			'name_en' => 'required|string|max:50',
			'name_km' => 'nullable|string|max:50',
			'descrioption_en' => 'nullable|string|max:250',
			'description_km' => 'nullable|string|max:250'
		]);
    }
    // Your service methods go here
    public function createDepartment(Request $req,$user){
        $validate = $this->DepartmentValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        Department::create($inputs);
        return DataResponse::JsonResult(null,false,"Created");
    }

    public function updateDepartment (Request $req,int $id,$user){
        $Department = Department::where('is_deleted',0)->find($id);
        if(!$Department) return DataResponse::NotFound("Department not found");
        $validate = $this->DepartmentValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $Department->update($inputs);
        return DataResponse::JsonResult(null,false,"Updated");
    }

    public function getDepartments(Request $req,$user){
        $Department = Department::query()->where('is_deleted',0);
        return DataResponse::PaginationV1($Department,$req);
    }

    public function getOneDepartment(int $id,$user){
        $Department = Department::where('is_deleted',0)->find($id);
        if(!$Department) return DataResponse::NotFound("Department not found");
        return DataResponse::JsonResult($Department,false,"get one Department");
    }

    public function deleteDepartment(int $id,$user){
        $Department = Department::where('is_deleted',0)->find($id);
        if(!$Department) return DataResponse::NotFound("Department not found");
        $Department->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,false,"Deleted");
    }
}
