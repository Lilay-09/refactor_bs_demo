<?php

namespace App\Services;

interface DriverCommissionService
{
    // Your service methods go here
    public function getDriverCommissionPackage(array $data): mixed;
    public function disbursementCommission(array $data,$user,$type): mixed;
}