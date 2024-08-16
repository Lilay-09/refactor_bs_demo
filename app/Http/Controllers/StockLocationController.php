<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Services\UserService;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    private function stockLocationValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:250',
            'address' => 'nullable|string|max:150',
            'address_kh' => 'nullable|string|max:150',
            'type_id' => 'required|int|exists:stock_location_types,id'
        ]);
    }
    //
    public function createStockLocation(Request $req){
        $user = UserService::getAuthUser();
        $userId = $user->id;
        $validator = $this->stockLocationValidation($req);
        if($validator->fails()) return ApiResponse::ValidateFail($validator->errors()->first());
        $inputs = $validator->validated();
        $inputs['create_uid'] = $userId;
        $inputs['update_uid'] = $userId;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;

        $create = StockLocation::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function getStockLocations(Request $req){
        $user = UserService::getAuthUser();
        $rows = StockLocation::where('branch_id',$user->branch_id)->get();
        return ApiResponse::JsonResult($rows);
    }
    public function getStockLocation(Request $req,$id){
        $user = UserService::getAuthUser();
        $rows = StockLocation::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($rows);
    }

    public function updateStockLocation(Request $req,$id){
        $user = UserService::getAuthUser();
        $stockLocation = StockLocation::where('branch_id',$user->branch_id)->find($id);
        if(!$stockLocation) return ApiResponse::NotFound('Location not found');
        $validator = $this->stockLocationValidation($req);
        if($validator->fails()) return ApiResponse::ValidateFail($validator->errors()->first());
        $inputs = $validator->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $update = $stockLocation->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
