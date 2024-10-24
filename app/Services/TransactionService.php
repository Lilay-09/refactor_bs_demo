<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Payment;
use DataResponse;
use DB;
use Exception;
use Illuminate\Http\Request;

class TransactionService
{
    // Your service methods go here'
    public function receivePaymentValidation(Request $req,$type='driver'){
        return validator($req->all(),[
            $type.'_id' => 'required|int',
            'remarks' => 'nullable|string|max:500',
            'packages' => 'required|array',
            'exchange_rate' => 'nullable|string'
        ]);
    }

    //* type must be one of driver or merchant
    public function receiverPaymentService(Request $req,$user,$type){
        $validate = self::receivePaymentValidation($req,$type);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $payerId = $inputs['driver_id'] ?? $inputs['merchant_id'];
        $packages = $inputs['packages'];
        $validPackages = $this->validPackages($packages,$payerId);
        return $validPackages;
        DB::beginTransaction();
        try{
            $createPayment = Payment::create([
                'payer_id' => $payerId,
                'create_uid' => $user->id,
                'update_uid' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);

            return $inputs;
        }catch(Exception $e){
            DB::rollBack();
        }
    }

    public function validPackages($packageIds,$driverId){
        $totalPackages = 0;
        $totalCod = 0;
        $totalDeliveryFee = 0;
        $deliveredPackageCount = 0;
        $driverTotal = 0;
        $merchantTotal = 0;
        foreach($packageIds as $key=>$id){
            $package = Package::where('driver_id',$driverId)->where('is_deleted',0)->whereIn('status_id',[9,19])->find($id);
            if(!$package){
                return DataResponse::ValidateFail(__('messages.info',['info' => 'Invalid package'.' on row ('.($key+1).')']));
            }
            if($package->status_id == 9) $deliveredPackageCount += 1;
            $totalDeliveryFee += $package->delivery_fee;
            // $calPackage = GeneralSettingService::calculatePackageFee($package->zone_code,$package->price,$package->billed_kg,$package->actual_kg,$package->payer,$package->cod);
            $totalPackages += 1;
            $driverTotal += $package->driver_total;
            $merchantTotal += $package->merchant_total;
        }
        return DataResponse::JsonRaw([
            'error' => false,
            'pacakage_ids' => $packageIds,
            'total_delivery_fee' => number_format($totalDeliveryFee,2),
            'total_package' => $totalPackages,
            'driver_total' => $driverTotal,
            'merchant_total' => $merchantTotal,
            'total_cod' => $totalCod,
            'delivered_package_count' => $deliveredPackageCount
        ]);
    }

}
