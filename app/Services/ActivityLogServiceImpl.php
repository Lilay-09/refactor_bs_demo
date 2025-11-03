<?php

namespace App\Services;

use App\Models\ActivityLog;
use DataResponse;

class ActivityLogServiceImpl implements ActivityLogService
{
    public function getActivities(array $filter)
    {
        $query = ActivityLog::query()
        ->select([
            'id','ref_code','before','after','metadata'
        ]);
        return DataResponse::PaginationV1($query,$filter,'',[],1000);
    }
}
