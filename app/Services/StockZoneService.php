<?php

namespace App\Services;
use Illuminate\Http\Request;
use App\Models\StockZone;
use DataResponse;

class StockZoneService
{
    private function StockZoneValidation(Request $req){
        return validator($req->all(),[
			'name' => 'required|string|max:50',
			'type' => 'nullable',
			'description' => 'nullable|max:250',
			'warehouse_id' => 'required|int'
		]);
    }
    // Your service methods go here
    public function createStockZone(Request $req,$user){
        $validate = $this->StockZoneValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        StockZone::create($inputs);
        return DataResponse::JsonResult(null,"Created");
    }

    public function updateStockZone (Request $req,int $id,$user){
        $StockZone = StockZone::where('is_deleted',0)->find($id);
        if(!$StockZone) return DataResponse::NotFound("StockZone not found");
        $validate = $this->StockZoneValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $StockZone->update($inputs);
        return DataResponse::JsonResult(null,"Updated");
    }

    public function getStockZones(Request $req,$user){
        $StockZone = StockZone::query()->where('is_deleted',0);
        return DataResponse::PaginationV1($StockZone,$req);
    }

    public function getOneStockZone(int $id,$user){
        $StockZone = StockZone::where('is_deleted',0)->find($id);
        if(!$StockZone) return DataResponse::NotFound("StockZone not found");
        return DataResponse::JsonResult($StockZone,"get one StockZone");
    }

    public function deleteStockZone(int $id,$user){
        $StockZone = StockZone::where('is_deleted',0)->find($id);
        if(!$StockZone) return DataResponse::NotFound("StockZone not found");
        $StockZone->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime'=> now()
        ]);
        return DataResponse::JsonResult(null,"Deleted");
    }
}
