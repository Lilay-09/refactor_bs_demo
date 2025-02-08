<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    //
    protected $imgDir = 'promotion';

    public function promotionValidation(Request $req){
        return validator($req->all(),[
            'title' => 'required|string|max:100',
            'description' => 'required|string|max:1000',
            'expires_days' => 'required|int',
            'photo' => 'nullable|string',
            'channel' => 'nullable|in:merchant,driver,sale'
        ]);
    }
    public function createPromotion(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->promotionValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $photo = $inputs['photo'] ?? null;
        $inputs['channel'] = $inputs['channel'] ?? 'merchant';
        $days = $inputs['expires_days'];
        $inputs['start_date'] = now();
        $inputs['end_date'] = Helper::getEndDate($days);
        $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir)->filename;
        Promotion::create($inputs);
        return ApiResponse::JsonResult(null,__('messages.created',[
            'info' => 'Promotion'
        ]));
    }

    public function getPromotions(Request $req){
        $user = UserService::getAuthUser();
        $promotions = Promotion::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('id,title,photo_file_name,description,start_date,end_date')
        ->get();
        foreach($promotions as $promotion){
            $promotion->image_url = Helper::getImageUrl($promotion->photo_file_name,$user->company_id,$this->imgDir);
            $promotion->expires_in = Helper::getAnalyzDiffDate($promotion->start_date,$promotion->end_date);
        }
        return ApiResponse::Pagination($promotions,__('messages.Get List',[
            'info' => 'Promotion'
        ]));
    }

    public function getOnePromotion(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $promotion = Promotion::where('company_id',$user->company_id)->where('is_deleted',0)
        ->selectRaw('id,title,photo_file_name,description,updated_at,start_date,end_date')
        ->find($id);
        if(!$promotion) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Promotion'
        ]));
        $promotion->image_url = Helper::getImageUrl($promotion->photo_file_name,$user->company_id,$this->imgDir);
        $promotion->expires_days = Helper::getDateDifference($promotion->start_date,$promotion->end_date,'days');
        return ApiResponse::JsonResult($promotion,__('messages.get one',[
            'info' => 'Promotion'
        ]));
    }


    public function updatePromotion(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $promotion = Promotion::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$promotion) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Promotion'
        ]));
        $validate = $this->promotionValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $photo = $inputs['photo'] ?? null;
        $days = $inputs['expires_days'];
        $inputs['start_date'] = now();
        $inputs['end_date'] = Helper::getEndDate($days);
        if(Helper::isValidBase64Image($photo) || !$photo){
            $imgFile = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir)->filename;
            if($imgFile) $inputs['photo_file_name'] = $imgFile;
            else $inputs['photo_file_name'] = null;
            Helper::deleteImageFile($promotion->photo_file_name,$user->company_id,$this->imgDir);
        }
        $promotion->update($inputs);
        return ApiResponse::JsonResult(null,__('messages.updated',[
            'info' => 'Promotion'
        ]));
    }

    public function deletePromotion(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $promotion = Promotion::where('company_id',$user->company_id)->where('is_deleted',0)
        ->find($id);
        if(!$promotion) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Promotion'
        ]));
        Helper::deleteImageFile($promotion->photo_file_name,$user->company_id,$this->imgDir);
        $promotion->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);

        return ApiResponse::JsonResult(null,__('messages.deleted',[
            'info' => 'Promotion'
        ]));

    }
}
