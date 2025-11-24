<?php

namespace App\Services;

interface PackageLogService
{
    //
    public function getPackageLogInfo(array $filters);
    public function getPackageLogInfoById(int $id);
}
