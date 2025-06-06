<?php

namespace App\Services;

use App\Models\UserNotificationToken;
use DataResponse;
use DB;
use Illuminate\Http\Request;

class UserNotificationServiceImpl implements UserNotificationService
{
    // Your service methods go here
    public function getUserNotificationSubscriptions(Request $req,object $authUser): object{
        $deviceCounts = UserNotificationToken::select('user_id', DB::raw('COUNT(*) as device_count'))
        ->groupBy('user_id')
        ->pluck('device_count', 'user_id');
        $query = UserNotificationToken::query()
        ->with('user:id,username,phone,account_type');
        $select = ['id','service_name','user_id'];
        $callback = function($q) use($deviceCounts){
            $q->username = $q->user->username;
            $q->phone = $q->user->phone;
            $q->device_count = $deviceCounts[$q->user_id] ?? 0;
            $q->makeHidden('user');
            return $q;
        };
        return DataResponse::PaginationV1($query,$req,'',[],200,$callback,$select);
    }
}
