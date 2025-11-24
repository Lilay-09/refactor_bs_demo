<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Package;
use DataResponse;

class ActivityLogServiceImpl implements ActivityLogService
{
    public function getActivities(array $filter)
    {
        $query = ActivityLog::query()
        ->orderBy('id','desc')
        ->select([
            'id','ref_code','before','after','metadata','ref_id',
        ]);
        return DataResponse::PaginationV1($query,$filter,'',[],1000);
    }

    public function getPackageLogInfoByPackageId(int $packageId){
        $query = ActivityLog::query()
        ->where('ref_id',$packageId)
        ->where('module','package')
        ->select([
            'id','ref_code','before','after','metadata','ref_id',
        ])
        ->orderBy('id','desc');
        return DataResponse::PaginationV1(query:$query);
    }

    public function getPackageLogInfo(array $filters){
        $select = [
            'id','zone_name','zone_code','qr_code','receiver_phone','merchant_id'
        ];
        $query = Package::query()
        ->with([
            'merchant:id,username,code,phone'
        ])
        ->orderBy('id','desc');
        $callbackFunc = function($q){
            $q->merchant_name = $q->merchant->username;
            $q->merchant_phone = $q->merchant->phone;
            return $q;
        };  
        return DataResponse::PaginationV1(query:$query,filter:$filters,select:$select,transformCallback:$callbackFunc);
    }
}
