<?php

namespace App\Services;

use App\Models\Package;

class PackageLogServiceImpl
{
    // Your service methods go here

    public function saveLog(array $packageInfo,string $logReason){
        $packageInfo['log_reason'] = $logReason;
    }
}
