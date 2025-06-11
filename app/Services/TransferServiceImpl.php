<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageTransfer;
use App\Models\PackageTransferDetail;
use DataResponse;
use DB;
use Exception;
use Illuminate\Http\Request;

class TransferServiceImpl implements TransferService
{
    // Your service methods go here

    private function transferValidator(Request $req){
        return validator($req->all(),[
            'from_location_id' => 'required|int',
            'to_location_id' => 'required|int',
            'transfer_date' => 'required|string',
            'est_arrive_date' => 'nullable|string',
            'driver_id' => 'nullable',
            'driver_name' => 'required|string',
            'driver_phone' => 'required|string',
            'status_id' => 'nullable',
            'location_type' => 'nullable',
            'transfer_items' => 'required|array',
            'transfer_items.*.package_id' => 'required|int',
        ]);
    }
    public function createTransfer(Request $req,object $authUser):object{

        $validator = $this->transferValidator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        $inputs = $validator->validated();
        $branchId = $authUser->branch_id;
        $companyId = $authUser->company_id;
        $userId = $authUser->id;
        $inputs['create_uid'] = $userId;
        $inputs['company_id'] = $companyId;
        $inputs['branch_id'] = $branchId;
        $inputs['update_uid'] = $userId;

        $transferItems = $inputs['transfer_items'];
        $pckIds = array_column($transferItems,'package_id');
        $packagesByKey = Package::where('is_deleted',false)
        ->where('warehouse_id',$inputs['from_location_id'])
        ->whereIn('status_id',[5,10])
        ->whereIn('id',$pckIds)->get()->keyBy('id');
        $insertTransferItems = [];
        foreach($transferItems as $idx => $item){
            $pkId = $item['package_id'];
            if(!isset($packagesByKey[$pkId])){
                return DataResponse::NotFound(__('messages.info',[
                    'info' => 'Item not found on row => '.($idx+1),
                    'khInfo' => 'រកមិនឃើញកញ្ចប់ជួរទី => '.($idx+1),
                ]));
            }
            $insertTransferItems[] = [
                'update_uid' => $userId,
                'create_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'package_id' => $pkId
            ];

        }
        // unset($inputs['transfer_items']);
        try{
            DB::beginTransaction();
            $pkTransfer = PackageTransfer::create(array_diff_key($inputs, ['transfer_items']));
            if ($pkTransfer && !empty($insertTransferItems)) {
                // Assign transfer ID in one map pass
                foreach ($insertTransferItems as &$item) {
                    $item['package_transfer_id'] = $pkTransfer->id;
                }
                unset($item);

                PackageTransferDetail::insert($insertTransferItems);
            }
            // DB::commit();
        }catch(Exception $e){
            DB::rollBack();
            return DataResponse::Error(__('messages.error'));
        }
        return DataResponse::JsonResult(null,false,__('messages.created'));
    }

    public function updateTransfer(int $id, Request $req, object $authUser): object{

    }

    public function getTransfers(Request $req, object $authUser): object{

    }
}
