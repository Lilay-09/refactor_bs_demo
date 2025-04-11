<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\ScoringReward;
use DataResponse;

class ScoringRewardService
{
    private function ScoringRewardValidation(Request $req){
        return validator($req->all(),[
			'code' => 'required|string|max:50',
			'mission' => 'nullable|string',
			'description' => 'nullable|string|max:250',
			'message' => 'nullable|string|max:150',
			'channel' => 'required|string',
			'start_date' => 'nullable',
			'expiration_date' => 'nullable',
			'image' => 'nullable|string',
			'reward_type' => 'nullable|string',
			'amount' => 'nullable|numeric',
			'claim_type_id' => 'required|int',
			'unit' => 'string',
			'list' => 'nullable|array',
			'max_usage' => 'nullable'
		]);
    }
    // Your service methods go here
    public function createScoringReward(Request $req,$user){
        $validate = $this->ScoringRewardValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['list'] = $inputs['list'] ? json_encode($inputs['list']):null;
        ScoringReward::create($inputs);
        return DataResponse::JsonResult(null,false,"Created");
    }

    public function updateScoringReward (Request $req,int $id,$user){
        if($id < 2) return DataResponse::NotFound();
        $ScoringReward = ScoringReward::where('is_deleted',0)->find($id);
        if(!$ScoringReward) return DataResponse::NotFound("ScoringReward not found");
        $validate = $this->ScoringRewardValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $ScoringReward->update($inputs);
        return DataResponse::JsonResult(null,false,"Updated");
    }

    public function getScoringRewards(Request $req,$user){
        $ScoringReward = ScoringReward::query()->where('is_deleted',0)->where('id','>',1);
        $callback = function($reward){
            $reward->list = json_decode($reward->list);
            return $reward;
        };
        $select = ['id','code','channel','mission','message','description','start_date','amount','amount_type','unit_amount','unit','expiration_date','image','reward_type','currency_code','is_publish','claim_type_id','list','max_usage'];
        return DataResponse::PaginationV1($ScoringReward,$req,'',[],200,$callback,$select);
    }

    public function getOneScoringReward(int $id,$user){
        if($id<2) return DataResponse::NotFound('Not found');
        $ScoringReward = ScoringReward::where('is_deleted',0)
        ->select($select = ['id','code','channel','mission','message','description','start_date','amount','amount_type','unit_amount','unit','expiration_date','image','reward_type','currency_code','is_publish','claim_type_id','list','max_usage'])
        ->find($id);
        if(!$ScoringReward) return DataResponse::NotFound("ScoringReward not found");
        return DataResponse::JsonResult($ScoringReward,false,"get one ScoringReward");
    }

    public function deleteScoringReward(int $id,$user){
        $ScoringReward = ScoringReward::where('is_deleted',0)->find($id);
        if(!$ScoringReward) return DataResponse::NotFound("ScoringReward not found");
        $ScoringReward->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,false,"Deleted");
    }
}
