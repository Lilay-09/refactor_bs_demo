<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SocialMedia;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class SocialMediaController extends Controller
{
    //
    protected $imgDir = 'social_media';
    public function socialMediaValidation(Request $req){
        return validator($req->all(),[
            'url' => 'nullable|string',
            'name' => 'required|string|max:50',
            'photo' => 'nullable|string'
        ]);
    }

    public function createSocialMedia(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->socialMediaValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->id;
        $inputs['company_id'] = $user->id;
        $photo = $inputs['photo'] ?? null;
        unset($inputs['photo']);
        $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir)->filename;
        SocialMedia::create($inputs);
        return ApiResponse::JsonResult(null,__('messages.created',[
            'info' => 'Social media'
        ]));
    }

    public function getSocialMedias(Request $req){
        $user = UserService::getAuthUser();
        $socialMedias = SocialMedia::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,name,url,photo_file_name,updated_at')
        ->get();
        foreach($socialMedias as $socialMedia){
            $socialMedia->image_url = Helper::getImageUrl($socialMedia->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::Pagination($socialMedias,$req);
    }
    public function getOneSocialMedia(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $socialMedia = SocialMedia::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,name,url,photo_file_name,updated_at')
        ->find($id);
        if(!$socialMedia) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Social Media'
        ]));
        $socialMedia->image_url = Helper::getImageUrl($socialMedia->photo_file_name,$user->company_id,$this->imgDir);
        return ApiResponse::JsonResult($socialMedia,__('messages.get one'));
    }
    public function updateSocialMedia(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $socialMedia = SocialMedia::where('is_deleted',0)->where('company_id',$user->company_id)
        ->find($id);
        if(!$socialMedia) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Social Media'
        ]));
        $validate = $this->socialMediaValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->id;
        $inputs['company_id'] = $user->id;
        $photo = $inputs['photo'] ?? null;
        if(Helper::isValidBase64Image($photo) || !$photo){
            $imgFile = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir)->filename;
            if($imgFile) $inputs['photo_file_name'] = $imgFile;
            else $inputs['photo_file_name'] = null;
            Helper::deleteImageFile($socialMedia->photo_file_name,$user->company_id,$this->imgDir);
        }
        $socialMedia->update($inputs);
        return ApiResponse::JsonResult(null,__('messages.updated',[
            'info' => 'Social Media'
        ]));
    }
    public function deleteSocialMedia(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $socialMedia = SocialMedia::where('is_deleted',0)->where('company_id',$user->company_id)
        ->find($id);
        if(!$socialMedia) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Social Media'
        ]));
        Helper::deleteImageFile($socialMedia->photo_file_name,$user->company_id,$this->imgDir);
        $socialMedia->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,__('messages.deleted',[
            'info' => 'Social Media'
        ]));
    }
}
