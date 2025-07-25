<?php

namespace App\Services;

use App\Enums\TrackingStatus;
use App\Enums\TransferStatus;
use App\Models\Package;
use App\Models\PackageTransfer;
use App\Models\PackageTransferDetail;
use App\Models\PackageTransferReceive;
use App\Models\PackageTransferReceiveItem;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Http\Request;
use Log;

class TransferServiceImpl implements TransferService
{
    // Your service methods go here

    private function transferValidator(Request $req){
        $rules = [
            'from_location_id' => 'required|int',
            'to_location_id' => 'required|int',
            'transfer_date' => 'required|string',
            'est_arrive_date' => 'nullable|string',
            'status_id' => 'nullable',
            'location_type' => 'nullable',
            'transfer_items' => 'required|array',
            'remarks' => 'nullable|string|max:250',
            'vehicle_type' => 'nullable'
            // 'transfer_items.*.package_id' => 'required|int',
        ];

        $rules['driver_id'] = 'nullable';
        $rules['driver_name'] = 'required|string|max:50';
        $rules['driver_phone'] = 'required|string|max:15';
        $rules['plate_number'] = 'required|string|max:9';

        return validator($req->all(),$rules);
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
        $inputs['transfer_datetime'] = Helper::dateYMD($inputs['transfer_date']);
        $inputs['status_id'] = $inputs['status_id'] ?? TransferStatus::PENDING->value;
        $transferItemIds = $inputs['transfer_items'];
        $packagesByKey = Package::where('is_deleted',false)
        ->where('warehouse_id',$inputs['from_location_id'])
        ->whereIn('status_id',[5,10])
        ->whereIn('id',$transferItemIds)->get()->keyBy('id');
        $insertTransferItems = [];
        $logPck = [];
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
            $logPck[] = [

            ];
            $inputs['transfer_qty'] +=1;
        }
        unset($inputs['transfer_items']);

        try{
            DB::beginTransaction();
            $pkTransfer = PackageTransfer::create($inputs);
            if ($pkTransfer && !empty($insertTransferItems)) {
                $pkTransfer->code = Helper::generateCode('TRX',$pkTransfer->id);
                $pkTransfer->save();
                // Assign transfer ID in one map pass

                $pkTransferId = $pkTransfer->id;
                $isTransit = $inputs['status_id'] == TransferStatus::IN_TRANSIT->value;
                foreach ($insertTransferItems as &$item) {
                    $item['status_id'] = $isTransit ? TransferStatus::IN_TRANSIT->value : TrackingStatus::DRAFT->value;
                    $item['package_transfer_id'] = $pkTransferId;
                }
                unset($item);
                PackageTransferDetail::insert($insertTransferItems);

                DB::table('packages')
                ->where('is_deleted', false)
                ->where('warehouse_id', $inputs['from_location_id'])
                ->whereIn('id', $transferItemIds)
                ->update([
                    'prev_status_id' => DB::raw('status_id'),
                    'status_id' => $isTransit ? TrackingStatus::IN_TRANSIT->value : TrackingStatus::PENDING_DEL->value,
                ]);
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
        $validator = $this->transferValidator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        $inputs = $validator->validated();
        $branchId = $authUser->branch_id;
        $companyId = $authUser->company_id;
        $userId = $authUser->id;
        $inputs['company_id'] = $companyId;
        $inputs['branch_id'] = $branchId;
        $inputs['update_uid'] = $userId;
        $inputs['transfer_uid'] = $userId;
        $inputs['status_id'] = $inputs['status_id'] ?? TransferStatus::PENDING->value;
        $inputs['transfer_datetime'] = Helper::dateYMD($inputs['transfer_date']);
        $transferItemIds = $inputs['transfer_items'];
        $packagesByKey = Package::where('is_deleted',false)
        ->where('warehouse_id',$inputs['from_location_id'])
        ->whereIn('status_id',[5,10])
        ->whereIn('id',$transferItemIds)->get()->keyBy('id');
        $existsPackageByKey = PackageTransferDetail::where('is_deleted',false)
        ->where('package_transfer_id',$id)
        ->whereIn('package_id',$transferItemIds)->get()->keyBy('package_id');
        $insertTransferItems = [];
        // return DataResponse::JsonResult($existsPackageByKey);
        $inputs['transfer_qty'] = 0;
        if($inputs['from_location_id'] == $inputs['to_location_id']){
            return DataResponse::Duplicated(__('messages.info',[
                'info' => 'It seems like you try to transfer to the same location',
                'khInfo' => 'មិនអាចផ្ទេរទៅឃ្លាំងដូចគ្នា!'
            ]));
        }
        $transfer = PackageTransfer::where('is_deleted',false)->find($id);
        if(!$transfer){
            return DataResponse::NotFound();
        }
        foreach($transferItemIds as $idx => $itemId){
            if(isset($existsPackageByKey[$itemId])){
                continue;
            }
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
            $transfer->transfer_qty += 1;
        }
        unset($inputs['transfer_items']);

        try{
            DB::beginTransaction();
            if (!empty($insertTransferItems)) {
                // Assign transfer ID in one map pass
                $isTransit = $inputs['status_id'] == TransferStatus::IN_TRANSIT->value;
                foreach ($insertTransferItems as &$item) {
                    $item['package_transfer_id'] = $id;
                    $item['status_id'] = $isTransit ? TransferStatus::IN_TRANSIT->value : TrackingStatus::DRAFT->value;
                }
                unset($item);
                PackageTransferDetail::insert($insertTransferItems);

                DB::table('packages')
                ->where('is_deleted', false)
                ->where('warehouse_id', $inputs['from_location_id'])
                ->whereIn('id', $transferItemIds)
                ->update([
                    'prev_status_id' => DB::raw('status_id'),
                    'status_id' => $isTransit ? TrackingStatus::IN_TRANSIT->value : TrackingStatus::PENDING_DEL->value,
                ]);
            }
            $transfer->transfer_datetime = $inputs['transfer_datetime'];
            $transfer->remarks = $inputs['remarks'] ?? null;
            $transfer->save();
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.updated'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error'));
        }
    }

    public function getTransfers(Request $req, object $authUser): object{
        $qT = PackageTransfer::query()
        ->where('is_deleted',false)
        ->with([
            'fromWarehouse',
            'toWarehouse'
        ])->orderByDesc('id');
        // $select = ['id','','code','transfer_qty','transfer_out_qty','from_location_id','to_location_id','status_id','remarks'];
        $select = ['*'];
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
        ->select(['id','remarks','transfer_datetime as transfer_date','driver_name','driver_id','driver_phone','plate_number','vehicle_type','transfer_qty','transfer_out_qty','from_location_id','status_id','to_location_id'])
        ->find($id);
        if($transfer){
            $transfer->load([
                'transfer_items:id,package_transfer_id,package_id,status_id',
                'transfer_items.packageInfo:id,qr_code,zone_name,zone_code,merchant_id,receiver_phone,receiver_address,cod,price,remarks,driver_total as total',
                'transfer_items.packageInfo.merchant:id,username'
            ]);
            foreach($transfer->transfer_items as $item){
                $item->merchant_name = $item->packageInfo->merchant->username;
                $item->qr_code = $item->packageInfo->qr_code;
                $item->zone_name = $item->packageInfo->zone_name;
                $item->receiver_address = $item->packageInfo->receiver_address;
                $item->receiver_phone = $item->packageInfo->receiver_phone;
                $item->cod = $item->packageInfo->cod;
                $item->price = $item->packageInfo->price;
                $item->remarks = $item->packageInfo->remarks;
                $item->is_available = $item->status_id !== TrackingStatus::DELIVERED->value;
                $item->total = $item->packageInfo->total;
                $item->makeHidden([
                    'packageInfo',
                    'merchant'
                ]);
            }
        }
        return DataResponse::JsonResult($transfer);
    }

    public function deleteTransfer(int $id, object $authuser): object{
        $transfer = PackageTransfer::where('is_deleted',false)
        ->find($id);
        if(!$transfer){
            return DataResponse::NotFound();
        }
        if($transfer->status_id == TransferStatus::IN_TRANSIT->value){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Transfer is progressing, You cannot delete this',
                'khInfo' =>  'ការផ្ទេរកំពុងស្ថិតក្នុងដំណើរការមិនអាចលុបបាន'
            ]));
        }
        if($transfer->status_id == TransferStatus::DELIVERED->value){
            return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Transfer is already delivered, You cannot delete this',
                'khInfo' =>  'ការផ្ទេរបញ្ចប់មិនអាចលុបបាន'
            ]));
        }

        $transfer->load('transfer_items');

        $packageIds = $transfer->transfer_items->pluck('package_id');
        DB::transaction(function() use($packageIds,$transfer,$authuser){
            Package::where('is_deleted',false)
            ->whereIn('id',$packageIds)
            ->update([
                'status_id' => DB::raw('prev_status_id'),
            ]);

            $transfer->update([
                'is_deleted' => true,
                'deleted_uid' => $authuser->id,
                'deleted_datetime' => now()
            ]);
        });

        return DataResponse::JsonResult(null,false,__('messages.deleted'));
    }

    //** RECEIVE TRANSFE'prev_status_id' => DB::raw('status_id'),R */

    private function receivePackageValidator(Request $req){
        return validator($req->all(),[
            'receive_items' => 'required|array',
            // 'location_id' => 'required|int',
            // 'status_id' => 'required|int'
        ]);
    }

    public function driverScanReceive(object $authUser,int $packageId){
        $isInTransitPkg = PackageTransferDetail::where('package_id',$packageId)
        ->where('is_deleted',false)
        ->orderByDesc('id')
        ->first();
        if(!$isInTransitPkg){
            return DataResponse::JsonResult(null,false,'Passed');
        }
        $receiveTransfer = $this->createReceive($isInTransitPkg->package_transfer_id,new Request([
            'receive_items' => [$packageId]
        ]),$authUser);
        if($receiveTransfer->error) return $receiveTransfer;
        return DataResponse::JsonResult(null);

    }

    public function createReceive(int $id, Request $req, object $authUser): object{
        $validate = $this->receivePackageValidator($req);
        if($validate->fails()){
            return DataResponse::ValidateFail($validate->errors()->first());
        }
        $inputs = $validate->validated();
        $userId = $authUser->id;
        $companyId = $authUser->company_id;
        $branchId = $authUser->branch_id;
        $inputs['create_uid'] = $userId;
        $inputs['update_uid'] = $userId;
        $inputs['company_id'] = $companyId;
        $inputs['branch_id'] = $branchId;
        $inputs['receive_date'] = now();
        $transfer = PackageTransfer::where('is_deleted',false)
        ->find($id);
        if(!$transfer){
            return DataResponse::NotFound();
        }

        if($transfer->status_id == TransferStatus::DELIVERED->value){
            return DataResponse::Duplicated(__('messages.info',[
                'info' => 'This transfer is already completed',
                'khInfo' => 'This transfer is already completed'
            ]));
        }
        $inputs['package_transfer_id'] = $id;
        // if($inputs['location_id'] == $transfer->from_location_id){
        //     return DataResponse::Duplicated(__('info',[
        //         'info' => "Can't receive the same warehouse as transfer warehouse"
        //     ]));
        // }
        $receiveItemIds = $inputs['receive_items'];
        $qXPkg = PackageTransferDetail::where('is_deleted',false)
        ->where('package_transfer_id',$id)
        ->where('status_id','!=',TrackingStatus::DELIVERED->value)
        ->get();

        $clExistsPackage = clone $qXPkg;
        $xPkgCount = $clExistsPackage->count();
        $existsPackageByKey = $qXPkg->keyBy('package_id');
        $packagesByKey = Package::where('is_deleted',false)
        ->where('warehouse_id',$transfer->from_location_id)
        ->where('status_id',12)
        ->whereIn('id',$receiveItemIds)->get()->keyBy('id');
        $updatePkg = [];
        $allReceive = 0;
        foreach($receiveItemIds as $itemId){
            if(!isset($existsPackageByKey[$itemId])){
                ;
                $allReceive += 1;
                continue;
            }
            $availablePkg = $packagesByKey[$itemId] ?? null;

            if($availablePkg){
                $updatePkg[] = [
                    'package_id' => $itemId,
                ];
                $transfer->transfer_out_qty +=1;
                $remainingQty = $transfer->transfer_qty - $transfer->transfer_out_qty;
                if($remainingQty>0){
                    $allReceive +=1;
                }

            }
        }
        $inputs['qty'] = $transfer->transfer_out_qty;
        $inputs['receive_uid'] = $authUser->id;
        $inputs['from_location_id'] = $transfer->from_location_id;
        $toLocationId = $transfer->to_location_id;
        $inputs['location_id'] = $toLocationId;
        try{
            DB::beginTransaction();
            $receive = PackageTransferReceive::create($inputs);
            if($receive){
                $receive->code = Helper::generateCode('TRX',$receive->id);
                $receive->save();
                if(!empty($updatePkg)){
                    Package::where('is_deleted',false)
                    ->whereIn('id',$receiveItemIds)
                    ->update([
                        'driver_id' => null,
                        'status_id' => TrackingStatus::AT_WAREHOUSE->value,
                        'warehouse_id' => $toLocationId
                    ]);
                    foreach($updatePkg as &$pkg){
                        $pkg['package_transfer_receive_id'] = $receive->id;
                    }
                    unset($pkg);
                    PackageTransferDetail::whereIn('package_id',$receiveItemIds)
                    ->update([
                        'status_id' => TrackingStatus::DELIVERED->value
                    ]);
                    PackageTransferReceiveItem::insert($updatePkg);
                }
            }
            if($xPkgCount == count($receiveItemIds)){
                $transfer->status_id = TransferStatus::DELIVERED->value;
            }else{
                $transfer->status_id = TransferStatus::IN_TRANSIT->value;
            }

            $transfer->save();
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.updated'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error'));
        }
    }

    public function getReceiveTransfers(Request $req, object $authUser): object{
        $qR = PackageTransferReceive::query()
        ->where('is_deleted',false)
        ->with([
            'transfer:id,transfer_datetime,status_id',
            'warehouse:id,name_en',
            'fromWarehouse:id,name_en'
        ])
        ->orderByDesc('id');
        $select = ['id','code','location_id','from_location_id','qty','receive_date','package_transfer_id'];
        $callback = function($q){
            $q->warehouse_name = $q->warehouse->name_en;
            $q->from_warehouse_name = $q->fromWarehouse->name_en;
            if($q->transfer){
                $q->transfer_date = Helper::dateDMY($q->transfer->transfer_datetime);
                $q->status = TransferStatus::tryFrom($q->transfer->status_id)->label();
                $q->status_id = $q->transfer->status_id;
            }
            $q->makeHidden(['transfer','warehouse','fromWarehouse']);
            return $q;
        };
        return DataResponse::PaginationV1($qR,$req,'',[],1000,$callback,$select);
    }

    public function getReceiveTransferById(int $id, object $authUser): object{
        $rc = PackageTransferReceive::where('is_deleted',false)
        ->find($id);
        if($rc){
            $rc->load('receiveItems');
        }
        return DataResponse::JsonResult($rc);
    }
}
