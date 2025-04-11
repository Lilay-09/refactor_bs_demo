<?php

namespace App\Http\Controllers\V1;
use App\Services\ScoringRewardService;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Models\ScoringReward;
use ApiResponse;

class ScoringRewardController
{

    protected $authUser;
    protected $scoringRewardService;

    public function __construct(ScoringRewardService $scoringRewardService){
        $this->authUser = UserService::getAuthUser();
        $this->scoringRewardService = $scoringRewardService;
    }

    public function ScoringRewardValidation(Request $req){
        return validator($req->all(),[
			'message' => 'required|string|max:150',
            'code' => 'nullable|string',
            'description' => '',
			'channel' => 'required'
		]);
    }
    public function saveDriverScoringReward(Request $req){
        $validate = $this->ScoringRewardValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['id'] = 1;
        $inputs['company_id'] = $this->authUser->company_id;
        $inputs['branch_id'] = $this->authUser->branch_id;
        $inputs['update_uid'] = $this->authUser->id;
        $createUid = ScoringReward::where('id', 1)->take(1)->value('create_uid');
        $inputs['create_uid'] = $createUid ?? $this->authUser->id;
        ScoringReward::upsert($inputs,['id']);
        return ApiResponse::JsonResult(null,'Saved');
    }


    public function getOneDriverScoringReward(Request $req){
        // $user = UserService::getAuthUser();
        // $id = $req->id;
        $ScoringReward = ScoringReward::where('is_deleted',0)
        ->select('id','channel','code','message','description')
        ->find(1);
        if(!$ScoringReward) return ApiResponse::NotFound();
        return ApiResponse::JsonResult($ScoringReward,"get one");
    }


    public function createScoringReward(Request $req){
        return ApiResponse::flex($this->scoringRewardService->createScoringReward($req,$this->authUser));
    }

    public function getOneScoringReward(Request $req){
        return ApiResponse::flex($this->scoringRewardService->getOneScoringReward($req->id,$this->authUser));
    }

    public function getScoringReward(Request $req){
        return ApiResponse::flex($this->scoringRewardService->getScoringRewards($req,$this->authUser));
    }

    public function updateScoringReward(Request $req){
        return ApiResponse::flex($this->scoringRewardService->updateScoringReward($req,$req->id,$this->authUser));
    }
    public function deleteScoringReward(Request $req){
        return ApiResponse::flex($this->scoringRewardService->deleteScoringReward($req->id,$this->authUser));
    }

}
