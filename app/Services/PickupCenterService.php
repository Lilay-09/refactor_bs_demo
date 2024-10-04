<?php

namespace App\Services;
use App\Models\Package;
use DataResponse;
use Helper;
use Request;

class PickupCenterService
{
    // Your service methods go here

    public function packageValidation(Request $req){
        return validator($req->all(),[
            'package_name' => 'nullable|string|max:100',
            'product_type' => 'nullable|string',
            'price' => 'nullable|numeric',
            'dim_z' => 'nullable|numeric',
            'dim_y' => 'nullable|numeric',
            'dim_x' => 'nullable|numeric',
            'status_id' => 'nullable|int',
            'failure_notes' => 'nullable|string|max:250',
            'order_id' => 'required|int',
            'payer' => 'required|in:sender,receiver',
            'cod' => 'required|in:0,1',
            'receiver_address' => 'nullable|string',
            'zone_code' => 'required|string',
            'receiver_phone' => 'required|string',
            'actual_kg' => 'nullable|numeric',
            'billed_kg' => 'nullable|numeric',
            'delivery_type' => 'nullable|string',
        ]);
    }

    public function createOrUpdatePackage(Request $req,$user,$packageId=null){
        $validate = $this->packageValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;

        if(!$packageId){
            $createPackage = Package::create($inputs);
            if(!$createPackage) return DataResponse::Error('Fail to create package');
            $qrCode = Helper::generateBarcodeString($createPackage->id,$user->company_id);
            Package::find($createPackage->id)->update([
                'qr_code' => $qrCode
            ]);
            return DataResponse::JsonResult(null,false,'Created');
        }else{
            $package = Package::where('is_deleted',0)->find($packageId);
            if(!$package) return DataResponse::NotFound('Package not found');
            $package->update($inputs);
        }

    }

    public function addOrUpdateOrderPackages($packages,$orderId){

    }
}
