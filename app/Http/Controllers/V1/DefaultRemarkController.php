<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DefaultRemark;
use App\Services\UserService;
use Illuminate\Http\Request;

class DefaultRemarkController extends Controller
{
    //

    public function defaultRemarkValidation(Request $req){
        return validator($req->all(),[
            'remarks' => 'required|string|max:100',
            'channel' => 'nullable|in:merchant,driver',
            'category' => 'required|in:failure,delivered,fail with fee',
        ]);
    }

    public function createDefaultRemark(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->defaultRemarkValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->id;
        $inputs['company_id'] = $user->id;
        $inputs['channel'] = $inputs['channel'] ?? 'driver';
        $existsName = DefaultRemark::where('remarks', $inputs['remarks'])->where('channel',$inputs['channel'])->first();
        if($existsName) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'remarks is already defined'
        ]));
        DefaultRemark::create($inputs);
        return ApiResponse::JsonResult(null,__('messages.created',[
            'info' => 'Remarks'
        ]));
    }

    public function getDefaultRemarks(Request $req){
        $user = UserService::getAuthUser();
        $category = $req->category;
        $dR = DefaultRemark::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,remarks,hidden,channel,category,updated_at');
        if($category){
            $dR->where('category',$category);
        }

        $defaultRemarks = $dR->get();
        return ApiResponse::Pagination($defaultRemarks,$req);
    }
    public function getOneDefaultRemark(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $defaultRemarks = DefaultRemark::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,remarks,hidden,category,channel,updated_at')
        ->find($id);
        if(!$defaultRemarks) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Remarks'
        ]));
        return ApiResponse::JsonResult($defaultRemarks,__('messages.get one'));
    }

    public function toggleHidden(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $defaultRemarks = DefaultRemark::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('hidden,id')
        ->find($id);
        $hidden = 'Hide';
        if(!$defaultRemarks) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Remarks'
        ]));
        if($defaultRemarks->hidden) {
            $hidden = 'Show';
            $defaultRemarks->update([
                'hidden' => 0
            ]);
        }
        else {
            $hidden = 'Hide';
            $defaultRemarks->update([
                'hidden' => 1
            ]);
        }
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Remarks '.$hidden
        ]));
    }

    public function updateDefaultRemark(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $defaultRemarks = DefaultRemark::where('is_deleted',0)->where('company_id',$user->company_id)
        ->find($id);
        if(!$defaultRemarks) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Remarks'
        ]));
        $validate = $this->defaultRemarkValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->id;
        $inputs['company_id'] = $user->id;
        $inputs['channel'] = $inputs['channel'] ?? 'driver';
        $existsName = DefaultRemark::where('remarks', $inputs['remarks'])
        ->where('id','!=',$id)
        ->where('channel',$inputs['channel'])->first();
        if($existsName) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'remarks is already defined'
        ]));
        $defaultRemarks->update($inputs);
        return ApiResponse::JsonResult(null,__('messages.updated',[
            'info' => 'Remarks'
        ]));
    }
    public function deleteDefaultRemark(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $defaultRemarks = DefaultRemark::where('is_deleted',0)->where('company_id',$user->company_id)
        ->find($id);
        if(!$defaultRemarks) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Remarks'
        ]));
        $defaultRemarks->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,__('messages.deleted',[
            'info' => 'Remarks'
        ]));
    }
}
