<?php

namespace App\Services\Mobile;

use App\Models\User;
use DataResponse;
use DB;
use Helper;

class AuthService
{
    // Your service methods go here
    public function getProfile($authUser,$userClass){
        $select = [
            'id','code','username','phone','email','address','photo_file_name','pin_address','latitude as loc_lat',
            'longitude as loc_lng','phone as phone_1'
        ];
        $phones = [];
        if($userClass == 'driver'){
            $select = array_merge($select,[
                // DB::raw('DATE(employment_date) as employment_date'),
                DB::raw("TO_CHAR(COALESCE(employment_date, CURRENT_DATE), 'DD-Mon-YYYY') as employment_date"),
                'relative_name',
                'relative_phone',
                'relative_relationship',
                'relative_address'
            ]);
            $phones = DB::table('user_contacts')
                ->where('user_id',$authUser->id)
                ->pluck('phone')->toArray();
        }
        $user = User::where('lock',0)->where('is_deleted',0)
        ->select($select)
        ->where('account_type',$authUser->account_type)
        ->find($authUser->id);
        if(!$user) return DataResponse::NotFound('User not found');
        $user->image_url = Helper::getImageUrl($user->photo_file_name,$authUser->company_id,'user_profile');
        if(!empty($phones)){
            foreach($phones as $idx=>$p){
                $user->{'phone_'.($idx + 2)} = $p;
            }
        }
        unset($user->photo_file_name);
        return DataResponse::JsonResult($user,__('messages.info',[
            'info' => 'Get Profile'
        ]));
    }
}
