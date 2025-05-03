<?php

namespace App\Services;

use App\Models\UserCommissionPerformance;
use DataResponse;
use Helper;
use Illuminate\Http\Request;


class UserCommissionPerformanceService
{
    // Your service methods go here
    private function commissionPerformanceValidator(Request $req){
        return validator($req->all(),[
            'earn_value' => 'int|min:0',
            'earn_type' => 'nullable|in:package,user',
            'rate_type' => 'nullable|in:percentage,amount',
            'rate_value' => 'int|min:0',
            'period_type' => [
                'string',
                Helper::enumValuesRule('PeriodType')
            ]
        ]);
    }

    public function saveCommissionPerformance(Request $req,$userId,$authUser): object {
        $validator = $this->commissionPerformanceValidator($req);
        if($validator->fails()) return DataResponse::ValidateFail($validator->errors()->first());
        $inputs = $validator->validated();
        $inputs['user_id'] = $userId;
        $inputs['update_uid'] = $authUser->id;
        $inputs['company_id'] = $authUser->company_id;
        $inputs['branch_id'] = $authUser->branch_id;
        if(!UserCommissionPerformance::where('user_id',$userId)->exists()){
            $inputs['create_uid'] = $authUser->id;
        }
        UserCommissionPerformance::upsert($inputs,['user_id']);
        return DataResponse::JsonResult(null,false);
    }
}
