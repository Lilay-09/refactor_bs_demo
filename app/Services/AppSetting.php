<?php

namespace App\Services;
use App\Models\PrivacyStatement;
use App\Models\TermCondition;
use DataResponse;
use Illuminate\Http\Request;
class AppSetting
{
    // Your service methods go here
    // protected static operation

    protected $notifTopics = [
        'admin' => [
            'prefix'
        ],
        'driver' => [],
        'merchant' => []
    ];


    private static function privacyTermConditionValidation(Request $req){
        return validator($req->all(),[
            'channel' => 'required|in:merchant,driver',
            'text' => 'nullable|string'
        ]);

    }
    public static function savePrivacyTermCondition(Request $req,$type,$user){
        $validate = self::privacyTermConditionValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $channel = $inputs['channel'];
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $modelName = null;
        $isUpdate = 1;
        if($type == 'privacy_statement'){
            $modelName = 'Privacy Statement';
            $model = PrivacyStatement::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)->first();
            if(!$model) {
                $isUpdate = 0;
                $model = new PrivacyStatement();
            }
        }else if('term_condition'){
            $modelName = 'Terms and conditions';
            $model = TermCondition::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)->first();
            if(!$model) {
                $isUpdate = 0;
                $model = new TermCondition();
            }
        }
        if($isUpdate){
            $model->update($inputs);
            return DataResponse::JsonResult(null,__('messages.updated',[
                'info' => $modelName
            ]));
        }else{
            $inputs['create_uid'] = $user->id;
            $model->create($inputs);
            return DataResponse::JsonResult(null,__('messages.created',[
                'info' => $modelName
            ]));
        }
    }

    public static function getPrivacyTermCondition($channel,$type,$user){
        $modelName = null;
        $model = null;
        if($type == 'privacy_statement'){
            $modelName = 'Privacy Statement';
            $model = PrivacyStatement::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)
            ->selectRaw('id,channel,text')
            ->first();
        }else if('term_condition'){
            $modelName = 'Terms and conditions';
            $model = TermCondition::where('channel',$channel)->where('company_id',$user->company_id)->where('is_deleted',0)
            ->selectRaw('id,channel,text')
            ->first();
        }
        if(!$model) return DataResponse::NotFound(__('messages.not_found',[
            'info' => $modelName
        ]));

        return DataResponse::JsonResult($model,__('messages.get one',[
            'info' => $modelName
        ]));
    }
}
