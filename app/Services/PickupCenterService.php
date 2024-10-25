<?php

namespace App\Services;
use App\Models\Order;
use App\Models\Package;
use App\Models\PriceList;
use App\Models\Zone;
use DataResponse;
use Helper;
use Illuminate\Http\Request;

class PickupCenterService
{
    // Your service methods go here

    public function packageValidation(Request $req){
        return validator($req->all(),[
            'photo_id' => 'nullable|int',
            'package_name' => 'nullable|string|max:100',
            'product_type' => 'nullable|string',
            'price' => 'nullable|numeric',
            'dim_z' => 'nullable|numeric',
            'dim_y' => 'nullable|numeric',
            'dim_x' => 'nullable|numeric',
            'status_id' => 'nullable|int',
            'failure_notes' => 'nullable|string|max:250',
            'payer' => 'required|in:sender,receiver',
            'cod' => 'required|in:0,1',
            'receiver_address' => 'nullable|string',
            'zone_code' => 'required|string|exists:zones,zone_code',
            // 'zone_id' => 'required|string',
            'receiver_phone' => 'required|string',
            'receiver_name' => 'nullable|string',
            'actual_kg' => 'nullable|numeric',
            'billed_kg' => 'nullable|numeric',
            'delivery_type' => 'required|in:fast,normal',
        ]);
    }

    // public function calculatePackageFee($zone_code,$price,$billedKg,$actualKg,$payer){
    //     $priceList = GeneralSettingService::getZonePriceByCode($zone_code);
    //     if(!$priceList) return DataResponse::NotFound('Zone price not found');
    //     $zPrice = $priceList->price > 0 ? $priceList->price : $priceList->base_fee;
    //     $selectKg = $billedKg ?? $actualKg;
    //     $additionalPrice = 0;
    //     $merchant_total = $zPrice;
    //     if($selectKg >= $priceList->above_kg){
    //         $additionalPrice = $priceList->above_kg_price;
    //     }else if($selectKg < $priceList->above_kg && $selectKg >= $priceList->below_kg){
    //         $additionalPrice = $priceList->below_kg_price;
    //     }

    //     $driverTotal = $price;
    //     $merchant_total += $additionalPrice;
    //     $total = $price + $additionalPrice;
    //     if($payer == 'receiver'){
    //         $total += $zPrice;
    //     }

    //     return (object)[
    //         'delivery_fee' => $zPrice,
    //         'driver_total' => $driverTotal,
    //         'merchant_total' => $merchant_total,
    //         'total' => $total
    //     ];
    // }

    public function updateOrderQty($orderId){
        $count = Package::where('is_deleted',0)->where('order_id',$orderId)->count();
        Order::where('is_deleted',0)->find($orderId)->update([
            'qty' => $count
        ]);
    }


    /**
     * Summary of createOrUpdatePackage
     * @param \Illuminate\Http\Request $req
     * @param mixed $user ** This one is auth user *SESSION*
     * @param mixed $packageId => it depends on action **IF UPDATE packageId must be provided
     * @param mixed $orderId => optional *-- might use only in pickup center module --*
     * @param mixed $statusIds => status can be differenct by module | By Default $statusIds=[1,7] = available for pick up or package is pending,
     * @param mixed $whereClause => for additional queries condition
     * @return object
     *
     *  => ------ for reusable on action update package --------
     */
    public function createOrUpdatePackage(Request $req,$user,$packageId=null,$orderId=null,$statusIds=[1,7],$whereClause=null){
        if($orderId){
            $order = Order::where('is_deleted',0)->find($orderId);
            if(!$order) return DataResponse::NotFound('Order not found');
        }
        $validate = $this->packageValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        if($orderId) $inputs['merchant_id'] = $order->merchant_id;
        $inputs['update_uid'] = $user->id;
        if($orderId) $inputs['order_id'] = $orderId;
        $price = $inputs['price'] ?? 0;
        $inputs['price'] = $price;
        $actualKg = $inputs['actual_kg'] ?? 0;
        $billedKg = $inputs['billed_kg'] ?? 0;
        $inputs['actual_kg'] = $actualKg;
        $payer = $inputs['payer'];
        $inputs['billed_kg'] = $actualKg;
        $cod = $inputs['cod'];
        $zoneCode = $inputs['zone_code'];
        $calPrice = GeneralSettingService::calculatePackageFee($zoneCode,$price,$billedKg,$actualKg,$payer,$cod);
        if($calPrice->error) return $calPrice;
        $inputs['driver_total'] = $calPrice->driver_total;
        $inputs['merchant_total'] = $calPrice->merchant_total;
        $inputs['delivery_fee'] = $calPrice->delivery_fee;
        $productType = $inputs['product_type'] ?? ($orderId ? $order->product_type:null);
        if(!$productType) unset($inputs['product_type']);
        if(!$packageId){
            $inputs['status_id'] = 7;
            $inputs['create_uid'] = $user->id;
            $createPackage = Package::create($inputs);
            if(!$createPackage) return DataResponse::Error(__('messages.Fail to create package'));
            $qrCode = Helper::generateBarcodeString($createPackage->id,$user->company_id);
            Package::find($createPackage->id)->update([
                'qr_code' => $qrCode
            ]);
            $this->updateOrderQty($orderId);
            return DataResponse::JsonResult(null,false,__('messages.created',['info' => 'Package Number ('.$qrCode.').']));
        }else{
            $qP = Package::where('is_deleted',0)->whereIn('status_id',$statusIds);
            if($whereClause){
                $qP->$whereClause;
            }
            $package = $qP->find($packageId);
            $inputs['status_id'] = $package->status_id;
            if(!$package) return DataResponse::NotFound(trans('messages.not_found',['info' => 'Package','khInfo' => 'កញ្ចប់']));
            // if($package->status_id == 5) return DataResponse::Forbidden(__('messages.no_access',['info' => 'This package has already assigned to driver']));
            $package->update($inputs);
            return DataResponse::JsonResult(null,false,__('messages.updated'));
        }
    }

}
