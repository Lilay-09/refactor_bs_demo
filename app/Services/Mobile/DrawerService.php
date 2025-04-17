<?php

namespace App\Services\Mobile;

use App\Models\UserZone;
use DataResponse;
use Helper;

class DrawerService
{
    // Your service methods go here
    public function getUserZone(int $userId,string $lang='en',string $useClass='driver'){
        $userZones = UserZone::where('user_id',$userId)
        ->select('id','user_id','zone_id','assign_at')
        ->with([
            'zone:id,zone_name,zone_code',
            'sub_zones:user_zone_id,zone_id'
        ])
        ->get()->map(function($uz) use($lang){
            $uz->zone_code = $uz->zone->zone_code;
            $uz->zone_name = $uz->zone->zone_name;
            $uz->assign_at = Helper::formatCustomDateTime($uz->assign_at,null,false,$lang);
            $uz->sub_zones->map(function($sb){
                $sb->zone_code = $sb->zone->zone_code;
                $sb->zone_name = $sb->zone->zone_name;
                $sb->makeHidden('zone');
                return $sb;
            });
            $uz->makeHidden('zone','id','zone_id','user_id');
            return $uz;
        });
        return DataResponse::JsonResult($userZones);
    }
}
