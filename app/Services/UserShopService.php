<?php

namespace App\Services;

use App\Enums\ImageDirectory;
use App\Models\UserShop;
use DataResponse;
use Helper;
use Illuminate\Http\Request;
use Log;

class UserShopService
{
    // Your service methods go here
    private function userShopValidation(Request $req){
        return validator($req->all(),[
            'owner_id' => 'required|int',
            'name_en' => 'required',
            'name_km' => 'nullable',
            'shop_type' => 'nullable',
            'address' => 'nullable',
            'product_type_id' => 'nullable|int',
            'pin_address' => 'nullable',
            'phone' => 'required',
            'email' => 'nullable',
            'disclaimer' => 'nullable',
            'country_id' => 'nullable',
            'city' => 'nullable',
            'district' => 'nullable',
            'commune' => 'nullable',
            'est_pcs' => 'nullable|numeric'
        ],[
            'name_en.required' => 'Shop Name (English) is required',
            'phone.required' => 'Shop contact is required'
        ]);
    }
    public function saveShop(Request $req,$user){
        // \Log::info($user);
        $validator = $this->userShopValidation($req);
        if($validator->fails()) return DataResponse::ValidateFail($validator->errors()->first());
        $inputs = $validator->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['country_id'] = 1; //* Default country
        // UserShop::upsert($inputs,['owner_id']);
        $userShop = UserShop::where('owner_id',$inputs['owner_id'])->first();
        if($userShop){
            $userShop->update($inputs);
        }else {
            $inputs['create_uid'] = $user->id;
            $userShop = UserShop::create($inputs);
        }
        return DataResponse::JsonResult(null,false,'Saved');
    }

    public function editMerchantShopLocation($authUser,int $merchantId,Request $data){
        $validator = validator($data->all(),[
            'image' => 'nullable',
            'pin_address' => 'nullable',
            'address' => 'required',
            'loc_lat' => 'required',
            'loc_lng' => 'required',
        ]);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $userShop = UserShop::where('owner_id',$merchantId)->first();
        $image = $inputs['image'] ?? null;
        unset($inputs['image']);
        if($userShop){
            // if(Helper::isValidBase64Image($image) || !$image){
            $imgFile = Helper::saveImageFileOrBase64($image,1,ImageDirectory::SHOP->value)->filename;
            if($imgFile){
                $inputs['image'] = $imgFile;
            }
            if(!$image){
                Log::info("Test");
                Helper::deleteImageFile($userShop->image,1,ImageDirectory::SHOP->value);
            }
            $userShop->update($inputs);
        }else {
            $inputs['create_uid'] = $authUser->id;
            $inputs['owner_id'] = $merchantId;
            $userShop = UserShop::create($inputs);
        }
        return DataResponse::JsonResult(null,false,__('messages.saved'));
    }

    public function getPickUpLocation(int $merchantId){
        $userShop = UserShop::where('owner_id',$merchantId)
        ->select(['pin_address','address','loc_lat','loc_lng','image'])
        ->first();
        if(!$userShop){
            return DataResponse::NotFound(__('messages.not_found'));
        }
        $userShop->image = Helper::getImageUrl($userShop->image,1,ImageDirectory::SHOP->value);
        return DataResponse::JsonResult($userShop);
    }
}
