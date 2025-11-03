<?php

namespace App\Services;

interface ActivityLogService
{
    public function getActivities(array $filter);
}
