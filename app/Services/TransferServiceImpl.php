<?php

namespace App\Services;

use App\Enums\Enums\TrackingStatus;
use App\Enums\TransferStatus;
use App\Models\Package;
use App\Models\PackageTransfer;
use App\Models\PackageTransferDetail;
use DataResponse;
use DB;
use Exception;
use Illuminate\Http\Request;
use Log;

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
            'remarks' => 'nullable|string|max:250'
            // 'transfer_items.*.package_id' => 'required|int',
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
        $inputs['transfer_uid'] = $userId;
        $inputs['status_id'] = $inputs['status_id'] ?? TransferStatus::PENDING->value;
        $transferItemIds = $inputs['transfer_items'];
        $packagesByKey = Package::where('is_deleted',false)
        ->where('warehouse_id',$inputs['from_location_id'])
        ->whereIn('status_id',[5,10])
        ->whereIn('id',$transferItemIds)->get()->keyBy('id');
        $insertTransferItems = [];
        $inputs['transfer_qty'] = 0;
        if($inputs['from_location_id'] == $inputs['to_location_id']){
            return DataResponse::Duplicated(__('messages.info',[
                'info' => 'It seems like you try to transfer to the same location',
                'khInfo' => 'មិនអាចផ្ទេរទៅឃ្លាំងដូចគ្នា!'
            ]));
        }
        foreach($transferItemIds as $idx => $itemId){
            if(!isset($packagesByKey[$itemId])){
                return DataResponse::NotFound(__('messages.info',[
                    'info' => 'Item not found on row => '.($idx+1),
                    'khInfo' => 'រកមិនឃើញកញ្ចប់ជួរទី => '.($idx+1)
                ]));
            }
            $insertTransferItems[] = [
                'update_uid' => $userId,
                'create_uid' => $userId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'package_id' => $itemId
            ];
            $inputs['transfer_qty'] +=1;
        }
        unset($inputs['transfer_items']);
        // return DataResponse::JsonResult($inputs);
        try{
            DB::beginTransaction();
            $pkTransfer = PackageTransfer::create($inputs);
            if ($pkTransfer && !empty($insertTransferItems)) {
                // Assign transfer ID in one map pass
                $pkTransferId = $pkTransfer->id;
                foreach ($insertTransferItems as &$item) {
                    $item['package_transfer_id'] = $pkTransferId;
                }
                unset($item);
                PackageTransferDetail::insert($insertTransferItems);
                if($inputs['status_id'] == TransferStatus::IN_TRANSIT->value){
                    Package::where('is_deleted',false)
                    ->where('warehouse_id',$inputs['from_location_id'])
                    ->whereIn('id',$transferItemIds)
                    ->update([
                        'status_id' => TrackingStatus::IN_TRANSIT->value
                    ]);
                }
            }
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.created'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error',[
                'info' => 'Something went wrong, It will work ASAP',
                'khInfo' => 'កំពុងមានបញ្ហាសូមរងចាំ'
            ]));
        }
    }

    public function updateTransfer(int $id, Request $req, object $authUser): object{

    }

    public function getTransfers(Request $req, object $authUser): object{
        $qT = PackageTransfer::query()
        ->where('is_deleted',false)
        ->with([
            'fromWarehouse',
            'toWarehouse'
        ]);
        $select = ['id','transfer_qty','transfer_out_qty','from_location_id','to_location_id','status_id','remarks'];
        $callback = function ($q){
            $q->append('remaining_qty');
            $q->append('status');
            $q->from_warehouse_name = $q->fromWarehouse?->name_en;
            $q->to_warehouse_name = $q->toWarehouse?->name_en;
            $q->makeHidden([
                'fromWarehouse',
                'toWarehouse'
            ]);
            return $q;
        };
        return DataResponse::PaginationV1($qT,$req,'',[],200,$callback,$select);
    }

    public function getOneTransfer(int $id, object $authUser): object{
        $transfer = PackageTransfer::where('is_deleted',false)
        ->select(['id','transfer_qty','transfer_out_qty','from_location_id','status_id','to_location_id'])
        ->with([
            'transfer_items:id,package_transfer_id,package_id'
        ])
        ->find($id);
        return DataResponse::JsonResult($transfer);
    }

    public function deleteTransfer(int $id, object $authuser): object{
        $transfer = PackageTransfer::where('is_deleted',false)
        ->find($id);
        if($transfer->status_id == TransferStatus::IN_TRANSIT->value){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Transfer is progressing, You cannot delete this',
                'khInfo' =>  'ការផ្ទេរកំពុងស្ថិតក្នុងដំណើរការមិនអាចលុបបាន'
            ]));
        }
        $transfer->update([
            'is_deleted' => false,
            'deleted_uid' => $authuser->id,
            'deleted_datetime' => now()
        ]);
        return DataResponse::JsonResult(null,false,__('messages.deleted'));
    }

    //** RECEIVE TRANSFER */

    private function receivePackageValidator(Request $req){
        return validator($req->all(),[
            'transfer_id' => 'required',
            'receive_items' => 'required|array',
            'location_id' => 'required|int',
            'status_id' => 'required|int'
        ]);
    }

    public function receivePackage(){

    }
    // public function getAvailableTransfer(Request $req, object $authUser): object{

    // }
}
