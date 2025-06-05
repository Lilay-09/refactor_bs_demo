<?php

namespace App\Services;

use Illuminate\Http\Request;

interface PickupCenterService
{
    //
    public function  createOrder(Request $req,object $user):object;

    public function createOrUpdatePackage(Request $req,$user,?int $packageId,?int $orderId,array $statusIds=[1,7],?callable $whereClause):object;
}
