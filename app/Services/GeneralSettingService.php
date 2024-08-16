<?php

namespace App\Services;
use App\Models\Vendor;

class GeneralSettingService
{
    // Your service methods go here
    public static function getOptionsVendor($user){
        return Vendor::where('branch_id',$user->branch_id)->selectRaw('id,name,phone')->get();
    }

}
