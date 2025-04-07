<?php

namespace App\Services\Mobile;

use App\Models\User;
use DataResponse;
use Helper;

class AuthService
{
    // Your service methods go here
    public function getProfile($authUser){
        $user = User::where('lock',0)->where('is_deleted',0)
        ->selectRaw('id,user_name,phone,email,address,photo_file_name,pin_address,latitude as loc_lat,longitude as loc_lng')
        ->where('account_type',$authUser->account_type)
        ->find($authUser->id);
        if(!$user) return DataResponse::NotFound('User not found');
        $user->image_url = Helper::getImageUrl($user->photo_file_name,$authUser->company_id,'user_profile');
        unset($user->photo_file_name);
        return DataResponse::JsonResult($user,__('messages.info',[
            'info' => 'Get Profile'
        ]));
    }
}
