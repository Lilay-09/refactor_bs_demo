<?php

namespace App\Services;

interface ActivityLogService
{
    public function getActivities(array $filter);
    public function getPackageLogInfo(array $filters);
    public function getPackageLogInfoByPackageId(int $packageId);
    
}
