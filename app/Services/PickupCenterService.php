<?php

namespace App\Services;

use Illuminate\Http\Request;

interface PickupCenterService
{
    //
    public function  createOrder(array $data,object $user):object;

    public function orderValidation(array $data);

    public function packageValidation(array $data);

    public function updateOrderQty($orderId,$count=null);

    public function createOrUpdatePackage(array $data,$user,?int $packageId,int $orderId,?array $statusIds=[1,7],?callable $whereClause=null):object;

    public static function getDriverTotal($cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$taxi=0,$otherFee=0);

    public static function getTotal($type,$cod,$payer,$price,$deliveryFee,$additional_fee,$extra_charge,$otherFee=0,$taxi=0);
    public function replaceOrderImage(object $user,array $data):object;

    public function deleteOrderImage(int $orderId,int $imageId):object;

    public static function getFees($payer,$deliveryFee,$additional_fee,$extra_charge,$pair='receiver');
}
