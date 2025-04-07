<?php

namespace App\Services;

use Illuminate\Http\Request;

class UserShopService
{
    // Your service methods go here
    private function userShopValidation(Request $req){
        return validator($req->all(),[
            'owner_id' => 'required',
            'name_en' => 'required',
            'name_km' => 'nullable',
            'shop_type' => 'nullable',
            'address' => 'nullable',
            'pin_address' => 'nullable',
            'phone' => 'required',
            'email' => 'nullable',
            'disclaimer' => 'nullable',
            'country_id' => 'nullable',
            'city' => 'nullable',
            'district' => 'nullable',
            'commune' => 'nullable'
        ]);
    }
    public function saveShop(Request $req,$user){

    }
}
