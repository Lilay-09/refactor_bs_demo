<?php

namespace App\Services;

use App\Models\UserShop;
use DataResponse;
use Illuminate\Http\Request;

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
            'pin_address' => 'nullable',
            'phone' => 'required',
            'email' => 'nullable',
            'disclaimer' => 'nullable',
            'country_id' => 'nullable',
            'city' => 'nullable',
            'district' => 'nullable',
            'commune' => 'nullable',
            'est_pcs' => 'nullable|numeric'
        ]);
    }
    public function saveShop(Request $req,$user){
        \Log::info($user);
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
}
