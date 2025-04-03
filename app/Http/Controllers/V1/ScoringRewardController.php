<?php

namespace App\Http\Controllers\V1;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Models\ScoringReward;
use ApiResponse;

class ScoringRewardController
{
    public function ScoringRewardValidation(Request $req){
        return validator($req->all(),[
			'message' => 'required|string|max:150',
			'channel' => 'required|email'
		]);
    }
    // Your service methods go here
    public function createScoringReward(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->ScoringRewardValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        ScoringReward::create($inputs);
        return ApiResponse::JsonResult(null,"Created");
    }

    public function updateScoringReward (Request $req){
        $user = UserService::getAuthUser();
        // $id = $req->id;
        $ScoringReward = ScoringReward::where('is_deleted',0)->find(1);
        if(!$ScoringReward) return ApiResponse::NotFound();
        $validate = $this->ScoringRewardValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $ScoringReward->update($inputs);
        return ApiResponse::JsonResult(null,"Updated");
    }

    public function getScoringRewards(Request $req){
        // $user = UserService::getAuthUser();
        $ScoringReward = ScoringReward::query()->where('is_deleted',0);
        return ApiResponse::PaginationV1($ScoringReward,$req);
    }

    public function getOneScoringReward(Request $req){
        // $user = UserService::getAuthUser();
        $id = $req->id;
        $ScoringReward = ScoringReward::where('is_deleted',0)
        ->select('id','channel','code','message','description')
        ->find(1);
        if(!$ScoringReward) return ApiResponse::NotFound();
        return ApiResponse::JsonResult($ScoringReward,"get one");
    }

    public function deleteScoringReward(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $ScoringReward = ScoringReward::where('is_deleted',0)->find($id);
        if(!$ScoringReward) return ApiResponse::NotFound();
        $ScoringReward->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return ApiResponse::JsonResult(null,"Deleted");
    }
}
