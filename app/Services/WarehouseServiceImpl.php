<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Warehouse;
use DataResponse;
use Illuminate\Http\Request;
use function Laravel\Prompts\select;

class WarehouseServiceImpl implements WarehouseService
{
    // Your service methods go here

    private function warehouseValidator(Request $req){
        return validator($req->all(),[
            'name_en' => 'required|string|max:50',
            'warehouse_type_id' => 'required|int',
            'branch_id' => 'required|int',
            'status_id' => 'required|int',
            'bm_name_en' => 'nullable|string|max:50',
            'bm_name_km' => 'nullable|string|max:50',
            'bm_phone' => 'nullable|string|max:25|min:9',
            'address_en' => 'nullable|string|max:250',
            'staff_count' => 'int|min:0'
        ]);
    }
    public function createWarehouse(Request $req, object $authUser): object{
        $validator = $this->warehouseValidator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $inputs['create_uid'] = $authUser->id;
        $inputs['update_uid'] = $authUser->id;
        $inputs['company_id'] = $authUser->company_id;

        if(Warehouse::where('is_deleted',false)->where('branch_id',$authUser->branch_id)->exists()){
            return DataResponse::Duplicated(__('messages.info',[
                'info' => 'You already have warehouse under this branch',
                'khInfo' => 'ឃ្លាំងមានរួចហើយ'
            ]));
        }
        Warehouse::create($inputs);
        return DataResponse::JsonResult(null,false,__('messages.created'));
    }
    public function getOneWarehouse(int $id, object $authUser): object{
        $warehouse = Warehouse::where('is_deleted',false)
        ->select('branch_id','id','name_en','bm_name_en','bm_phone','staff_count','warehouse_type_id','status_id')
        ->find($id);
        return DataResponse::JsonResult($warehouse,false);
    }
    public function getWarehouses(Request $req,object $authUser): object{
        $qW = Warehouse::query()
        ->where('is_deleted',false);
        $select = ['branch_id','id','name_en','bm_name_en','bm_phone','staff_count','address_en'];
        return DataResponse::PaginationV1($qW,$req,'',[],100,null,$select);
    }

    public function updateWarehouse(int $id, Request $req, object $authUser): object{
        $validator = $this->warehouseValidator($req);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }

        $inputs = $validator->validated();
        $inputs['create_uid'] = $authUser->id;
        $inputs['update_uid'] = $authUser->id;
        $inputs['branch_id'] = $authUser->branch_id;
        $inputs['company_id'] = $authUser->company_id;

        if(Warehouse::where('is_deleted',false)->where('id','!=',$id)->where('branch_id',$authUser->branch_id)->exists()){
            return DataResponse::Duplicated(__('messages.info',[
                'info' => 'You already have warehouse under this branch',
                'khInfo' => 'ឃ្លាំងមានរួចហើយ'
            ]));
        }
        Warehouse::create($inputs);
        return DataResponse::JsonResult(null,false,__('messages.created'));
    }


    public function getWarehousesByBranch(int $branchId, object $authUser): object{
        $warehouse = Warehouse::where('is_deleted',false)
        ->select('branch_id','id','name_en','bm_name_en','bm_phone','staff_count','warehouse_type_id')
        ->where('branch_id',$branchId)->get();
        return DataResponse::JsonResult($warehouse);
    }

    public function deleteWarehouse(int $id,object $authUser):object{
        $warehouse = Warehouse::where('is_deleted',false)->find($id);
        if(!$warehouse){
            return DataResponse::NotFound(__('messages.not_found'));
        }
        if(Package::where('is_deleted',false)->where('warehouse_id',$id)->exists()){
            return DataResponse::Forbidden(__('messages.info',[
                'info' => 'Package(s) was found in this warehouse, Could not delete this!',
                'khInfo' => 'មានកញ្ចប់ក្នុងឃ្លាំងមិនអាចលុបបានទេ'
            ]));
        }
        $warehouse->update([
            'is_deleted' => false,
            'deleted_uid' => $authUser->id,
            'deleted_datetime' => now()
        ]);

        return DataResponse::JsonResult(null,false,__('messages.deleted'));
    }

}
