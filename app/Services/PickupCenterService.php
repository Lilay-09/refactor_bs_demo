<?php

namespace App\Services;
use App\Models\Package;
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
            'payer' => 'required|in:merchant,receiver',
            'cod' => 'required|in:0,1',
            'receiver_address' => 'nullable|string',
            'zone_code' => 'required|string|exists:zones,zone_code',
            // 'zone_id' => 'required|string',
            'receiver_phone' => 'required|string',
            'receiver_name' => 'nullable|string',
            'actual_kg' => 'nullable|numeric',
            'billed_kg' => 'nullable|numeric',
            'delivery_type' => 'required|in:fast,normal',
            'additional_fee' => 'nullable|numeric'
        ]);
    }

    public function createOrUpdatePackage($orderId,Request $req,$user,$packageId=null){
        $validate = $this->packageValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['update_uid'] = $user->id;
        $inputs['order_id'] = $orderId;
        $price = $inputs['price'] ?? 0;
        $inputs['cod'] = 0;
        $inputs['price'] = $price;
        if($price) $inputs['cod'] = 1;
        $inputs['status_id'] = 5;

        if(!$packageId){
            $inputs['create_uid'] = $user->id;
            $createPackage = Package::create($inputs);
            if(!$createPackage) return DataResponse::Error(__('messages.Fail to create package'));
            $qrCode = Helper::generateBarcodeString($createPackage->id,$user->company_id);
            Package::find($createPackage->id)->update([
                'qr_code' => $qrCode
            ]);
            return DataResponse::JsonResult(null,false,__('messages.created'));
        }else{
            $package = Package::where('is_deleted',0)->find($packageId);
            if(!$package) return DataResponse::NotFound(__('messages.Package not found'));
            $package->update($inputs);
            return DataResponse::JsonResult(null,false,__('messages.updated'));
        }
    }

    public function addOrUpdateOrderPackages($packages,$orderId){

    }
}
