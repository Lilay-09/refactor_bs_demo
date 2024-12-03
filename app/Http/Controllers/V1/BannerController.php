<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    //
    protected $imgDir = 'banner';
    private function bannerValidation(Request $req){
        return validator($req->all(),[
            'photo' => 'nullable|string',
            'channel' => 'required|in:driver,merchant',
            'title' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:300'
        ]);
    }

    public function createBanner(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->bannerValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $photo = $inputs['photo'] ?? null;
        unset($inputs['photo']);
        $img = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
        $inputs['photo_file_name'] = $img->filename;
        Banner::create($inputs);
        return ApiResponse::JsonResult(null,__('messages.created',[
            'info' => 'Banner',
            'khInfo' => 'Banner'
        ]));
    }

    public function getBanners(Request $req){
        $user = UserService::getAuthUser();
        $channel = $req->channel;
        $qBI = Banner::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,channel,photo_file_name');
        if($channel) $qBI->where('channel',$channel);
        $banners = $qBI->get();
        foreach($banners as $brandImage){
            $brandImage->image_url = Helper::getImageUrl($brandImage->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::Pagination($banners,$req,__('messages.Get List'));
    }

    public function getOneBanner(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $banner = Banner::where('is_deleted',0)->where('company_id',$user->company_id)
        ->selectRaw('id,channel,photo_file_name')
        ->find($id);
        if(!$banner) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Banner'
        ]));
        $banner->image_url = Helper::getImageUrl($banner->photo_file_name,$user->company_id,$this->imgDir);
        return ApiResponse::JsonResult($banner,__('messages.get one'));
    }

    public function updateBanner(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $banner = Banner::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$banner) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Banner'
        ]));
        $validate = $this->bannerValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $photo = $inputs['photo'] ?? null;
        unset($inputs['photo']);
        if(Helper::isValidBase64Image($photo) || !$photo){
            $img = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
            $inputs['photo_file_name'] = $img->filename;
            Helper::deleteImageFile($banner->photo_file_name,$user->company_id,$this->imgDir);
        }
        $update = $banner->update($inputs);
        if(!$update) {
            Helper::deleteImageFile($inputs['photo_file_name'],$user->company_id,$this->imgDir);
            return ApiResponse::Error(__('messages.error',[
                'info' => 'Fail to create'
            ]));
        }
        return ApiResponse::JsonResult(null,__('messages.updated',[
            'info' => 'Banner'
        ]));
    }

    public function deleteBanner(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $banner = Banner::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$banner) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Banner'
        ]));
        Helper::deleteImageFile($banner->photo_file_name,$user->company_id,$this->imgDir);
        $banner->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id
        ]);

        return ApiResponse::JsonResult(null,__('messages.deleted',[
            'info' => 'Banner'
        ]));
    }
}
