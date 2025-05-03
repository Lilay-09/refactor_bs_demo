<?php

namespace App\Services;

use App\Enums\PeriodType;
use App\Models\UserTargetPolicy;
use DataResponse;
use Helper;
use Illuminate\Http\Request;

class UserTargetPolicyService
{
    // Your service methods go here

    private function userTargetPolicyValidator(Request $req){
        return validator($req->all(),[
            'target_value'         => 'nullable|integer|min:0',
            'target_type'          => 'nullable|string|in:package,user',
            'monthly_bonus'        => 'nullable|numeric|min:0',
            'monthly_bonus_type'   => 'nullable|string|in:percentage,amount',
            'yearly_bonus'         => 'nullable|numeric|min:0',
            'yearly_bonus_type'    => 'nullable|string|in:percentage,amount',
            'effective_date'       => 'nullable|date',
            'period_type'          => ['nullable', Helper::enumValuesRule(PeriodType::class)], // Or custom enum logic if needed
        ]);
    }

    public function saveUserTargetPolicy(Request $req,$userId,$authUser): object {
        $validator = $this->userTargetPolicyValidator($req);
        if($validator->fails()) return DataResponse::ValidateFail($validator->errors());
        $inputs = $validator->validated();
        $inputs['user_id'] = $userId;
        $inputs['update_uid'] = $authUser->id;
        $inputs['company_id'] = $authUser->company_id;
        $inputs['branch_id'] = $authUser->branch_id;
        $inputs['target_value'] = $inputs['target_value'] ?? 0;
        $inputs['monthly_bonus'] = $inputs['monthly_bonus'] ?? 0;
        $inputs['yearly_bonus'] = $inputs['yearly_bonus'] ?? 0;
        $inputs['monthly_bonus_type'] = $inputs['monthly_bonus_type'] ?? 'amount';
        $inputs['yearly_bonus_type'] = $inputs['yearly_bonus_type'] ?? 'amount';
        $inputs['effective_date'] = now();
        $userTargetPolicy = UserTargetPolicy::where('user_id',$userId)->first();
        if(!$userTargetPolicy){
            $inputs['create_uid'] = $authUser->id;
            UserTargetPolicy::create($inputs);
        }else{
            $userTargetPolicy->update($inputs);
        }
        return DataResponse::JsonResult(null,false);
    }

}
