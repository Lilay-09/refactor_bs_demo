<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    //
    public function __construct(private ActivityLogService $activityLogService)
    {
        
    }
    public function getActivities(Request $req){
        return ApiResponse::flex($this->activityLogService->getActivities($req->all()));
    }
}
