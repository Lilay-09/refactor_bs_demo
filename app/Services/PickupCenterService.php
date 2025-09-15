<?php

namespace App\Services;

use Illuminate\Http\Request;

interface PickupCenterService
{
    //
    public function  createOrder(Request $req,object $user):object;

    public function orderValidation(Request $req);

    public function packageValidation(Request $req);

    public function updateOrderQty($orderId,$count=null);

    public function createOrUpdatePackage(Request $req,$user,?int $packageId,int $orderId,array $statusIds=[1,7],?callable $whereClause):object;

    public static function getDriverTotal($cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$taxi=0,$otherFee=0);

    public static function getTotal($type,$cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$taxi=0);
    public function replaceOrderImage(object $user,Request $req):object;

    public static function getFees($payer,$deliveryFee,$additional_fee,$extra_charge,$pair='receiver');
}
