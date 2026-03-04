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
            'cover' => 'nullable|string',
            'channel' => 'required|in:driver,merchant',
            'title' => 'nullable|string|max:100',
            'title_km' => 'nullable|string',
            'description' => 'nullable|string',
            'description_km' => 'nullable|string',
            'contact_link' => 'nullable|string|max:250',
            'start_date' => 'nullable|string',
            'end_date' => 'nullable|string'
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
        $cover = $inputs['cover'] ?? null;
        unset($inputs['photo'],$inputs['cover']);
        $inputs['start_date'] = isset($inputs['start_date']) ? Helper::dateYMD($inputs['start_date']) : null;
        $inputs['end_date'] = isset($inputs['end_date']) ? Helper::dateYMD($inputs['end_date']) : null;
        $img = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
        $inputs['photo_file_name'] = $img->filename;

        $coverImg = Helper::base64ToImageFile($cover,$user->company_id,$this->imgDir);
        $inputs['cover_file_name'] = $coverImg->filename;
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
        ->select('id','channel','contact_link','photo_file_name','cover_file_name','title','description','description_km','title','end_date','start_date','is_publish');
        if($channel) $qBI->where('channel',$channel);
        $banners = $qBI->get();
        foreach($banners as $brandImage){
            $brandImage->start_date = Helper::dateDMY($brandImage->start_date);
            $brandImage->end_date = Helper::dateDMY($brandImage->end_date);
            $brandImage->image_url = Helper::getImageUrl($brandImage->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::Pagination($banners,$req,__('messages.Get List'));
    }

    public function getOneBanner(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $banner = Banner::where('is_deleted',0)->where('company_id',$user->company_id)
        ->select('id','channel','contact_link','photo_file_name','cover_file_name','title','title_km','description_km','description','end_date','start_date')
        ->find($id);
        if(!$banner) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Banner'
        ]));
        $banner->image_url = Helper::getImageUrl($banner->photo_file_name,$user->company_id,$this->imgDir);
        $banner->cover_url = Helper::getImageUrl($banner->cover_file_name,$user->company_id,$this->imgDir);
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
        $coverPhoto = $inputs['cover'] ?? null;
        unset($inputs['photo'],$inputs['cover']);
        if(Helper::isValidBase64Image($photo) || !$photo){
            $img = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
            $inputs['photo_file_name'] = $img->filename;
            Helper::deleteImageFile($banner->photo_file_name,$user->company_id,$this->imgDir);
        }
        if(Helper::isValidBase64Image($coverPhoto) || !$coverPhoto){
            $img = Helper::base64ToImageFile($coverPhoto,$user->company_id,$this->imgDir);
            $inputs['cover_file_name'] = $img->filename;
            Helper::deleteImageFile($banner->photo_file_name,$user->company_id,$this->imgDir);
        }
        $update = $banner->update($inputs);
        if(!$update) {
            Helper::deleteImageFile($inputs['photo_file_name'],$user->company_id,$this->imgDir);
            return ApiResponse::Error(__('messages.error',[
                'info' => 'Fail to create'
            ]));
        }
        return ApiResponse::JsonResult(null,__('messages.saved'));
    }

    public function togglePublishBanner(Request $req){
        $id = $req->id;
        $banner = Banner::where('is_deleted',0)
        ->find($id);
        if(!$banner) return ApiResponse::NotFound(__('messages.not_found',[
            'info' => 'Banner'
        ]));
        $banner->update([
            'is_publish' => !$banner->is_publish
        ]);
        return ApiResponse::JsonResult($banner,__('messages.get one'));
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
