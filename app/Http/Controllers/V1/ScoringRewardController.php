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
            'code' => 'nullable|string',
            'description' => '',
			'channel' => 'required'
		]);
    }
    public function saveScoringReward(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->ScoringRewardValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['id'] = 1;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['update_uid'] = $user->id;
        $createUid = ScoringReward::where('id', 1)->take(1)->value('create_uid');
        $inputs['create_uid'] = $createUid ?? $user->id;
        ScoringReward::upsert($inputs,['id']);
        return ApiResponse::JsonResult(null,'Saved');
    }


    public function getOneScoringReward(Request $req){
        // $user = UserService::getAuthUser();
        // $id = $req->id;
        $ScoringReward = ScoringReward::where('is_deleted',0)
        ->select('id','channel','code','message','description')
        ->find(1);
        if(!$ScoringReward) return ApiResponse::NotFound();
        return ApiResponse::JsonResult($ScoringReward,"get one");
    }


}
