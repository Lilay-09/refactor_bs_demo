<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BrandImage;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class BrandImageController extends Controller
{
    //

    protected $imgDir = 'brand_image';
    private function brandImageValidation(Request $req){
        return validator($req->all(),[
            'photo' => 'nullable|string',
            'channel' => 'required|in:driver,merchant',
            'title' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:300'
        ]);
    }

    public function createBrandImage(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->brandImageValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $photo = $inputs['photo'] ?? null;
        unset($inputs['photo']);
        $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
        BrandImage::create($inputs);
        return ApiResponse::JsonResult(null,__('messages.created',[
            'info' => 'Brand Image'
        ]));
    }

    public function getBrandImages(Request $req){
        $user = UserService::getAuthUser();
        $channel = $req->channel;
        $qBI = BrandImage::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,channel,photo_file_name');
        if($channel) $qBI->where('channel',$channel);
        $brandImages = $qBI->get();
        foreach($brandImages as $brandImage){
            $brandImage->image_url = Helper::getImageUrl($brandImage->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::Pagination($brandImages,$req,__('messages.Get List'));
    }

    public function getOneBrandImage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $brandImage = BrandImage::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,channel,photo_file_name')
        ->find($id);
        if(!$brandImage) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Brand Image'
        ]));
        $brandImage->image_url = Helper::getImageUrl($brandImage->photo_file_name,$user->company_id,$this->imgDir);
        return ApiResponse::JsonResult($brandImage,__('messages.get one'));
    }

    public function updateBrandImage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $brandImage = BrandImage::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$brandImage) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Brand Image'
        ]));
        $validate = $this->brandImageValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $photo = $inputs['photo'] ?? null;
        unset($inputs['photo']);
        if(Helper::isValidBase64Image($photo) || !$photo){
            $imgFile = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
            if($imgFile) $inputs['photo_file_name'] = $imgFile;
            else $inputs['photo_file_name'] = null;
            Helper::deleteImageFile($brandImage->photo_file_name,$user->company_id,$this->imgDir);
        }
        $update = $brandImage->update($inputs);
        if(!$update) {
            Helper::deleteImageFile($inputs['photo_file_name'],$user->company_id,$this->imgDir);
            return ApiResponse::Error(__('messages.error',[
                'info' => 'Fail to create'
            ]));
        }
        return ApiResponse::JsonResult(null,__('messages.updated',[
            'info' => 'Brand Image'
        ]));
    }

    public function deleteBrandImage(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $brandImage = BrandImage::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$brandImage) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Brand Image'
        ]));
        Helper::deleteImageFile($brandImage->photo_file_name,$user->company_id,$this->imgDir);
        $brandImage->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);

        return ApiResponse::JsonResult(null,__('messages.deleted',[
            'info' => 'Image'
        ]));
    }


}
