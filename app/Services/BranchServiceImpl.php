<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use DataResponse;
use Illuminate\Http\Request;

class BranchServiceImpl implements BranchService
{
    // Your service methods go here
    public function getBranches(Request $req, object $authUser): object{
        $qB = Branch::query()
        ->where('is_deleted',false);
        $select = ['id','name_en','address_en','bm_name_en','bm_phone','staff_count','emergency_phone','branch_type_id'];
        $callback = function ($q){
            $q->append('branch_type');
            return $q;
        };
        return DataResponse::PaginationV1($qB,$req,'',[],200,$callback,$select);
    }

    public function getOneBranch(int $id, object $authUser): object{
        $branch = Branch::where('is_deleted',false)
        ->select(['id','name_en','address_en','bm_name_en','bm_phone','staff_count','emergency_phone','branch_type_id'])
        ->find($id);
        return DataResponse::JsonResult($branch,false,__('messages.Get List'));
    }

    private function branchValidator(Request $req){
        return validator($req->all(),[
            'name_en' => 'required|max:100',
            'bm_name_en' => 'required|max:100',
            'bm_name_km' => 'nullable|max:100',
            'bm_phone' => 'nullable|max:100',
            'address_en' => 'nullable|max:250',
            'email' => 'nullable',
            'emergency_phone' => 'nullable|min:9|max:15',
            'staff_count' => 'nullable|int|min:0',
        ]);
    }

    public function createBranch(Request $req, object $authUser): object{
        $validator = $this->branchValidator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $inputs['create_uid'] = $authUser->id;
        $inputs['update_uid'] = $authUser->id;
        $inputs['branch_id'] = $authUser->branch_id;
        $inputs['company_id'] = $authUser->company_id;

        Branch::create($inputs);
        return DataResponse::JsonResult(null,false,__('messages.created'));
    }

    public function updateBranch(int $id, Request $req, object $authUser): object{
        $validator = $this->branchValidator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $inputs['update_uid'] = $authUser->id;
        $inputs['branch_id'] = $authUser->branch_id;
        $inputs['company_id'] = $authUser->company_id;
        $branch = Branch::where('is_deleted',false)->find($id);
        if(!$branch){
            return DataResponse::NotFound(__('messages.not_found'));
        }
        $branch->update($inputs);
        return DataResponse::JsonResult(null,false,__('messages.updated'));
    }

    public function deleteBranch(int $id,object $authUser): object{
        $branch = Branch::where('is_deleted',false)->find($id);
        if(!$branch){
            return DataResponse::NotFound(__('messages.not_found'));
        }
        if(User::where('is_deleted',false)->where('branch_id',$id)->exists()){
            return DataResponse::Forbidden(__('messages.info',[
                'Please ensure there is no staff under branch before proceed this!',
                'Please ensure there is no staff under branch before proceed this!'
            ]));
        }

        $branch->update([
            'is_deleted' => true,
            'deleted_uid' => $authUser->id,
            'deleted_datetime' => now()
        ]);

        return DataResponse::JsonResult(null,false,__('messages.deleted'));
    }


}
