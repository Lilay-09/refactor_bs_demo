<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StockLocation;
use App\Services\UserService;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{

    protected $warehouseLimiation = 3;

    private function stockLocationValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:250',
            'branch_id' => 'nullable|int|exists:branches,id',
            'address' => 'nullable|string|max:150',
            'address_kh' => 'nullable|string|max:150',
            'type_id' => 'required|int|exists:stock_location_types,id',
            'main' => 'nullable|in,true,false|default:false'
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
        $inputs['branch_id'] = isset($inputs['branch_id']) ? $inputs['branch_id'] : $user->branch_id;
        $isMain = $inputs['main'] ?? null;
        if($isMain){
            $hasMain = StockLocation::where('company_id',$user->company_id)->take(1)->value('main');
            if($hasMain) return ApiResponse::Duplicated('The main wareharehouse is already exists');
        }
        $count = StockLocation::where('company_id',$user->company_id)->count();
        if($count == $this->warehouseLimiation) return ApiResponse::ValidateFail('Warehouse has reached limit '.$this->warehouseLimiation.' of '.$this->warehouseLimiation);
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
        $inputs['branch_id'] = isset($inputs['branch_id']) ? $inputs['branch_id'] : $user->branch_id;
         $hasMain = StockLocation::where('company_id',$user->company_id)->where('id','!=',$id)->take(1)->value('main');
        if($hasMain) return ApiResponse::Duplicated('The main wareharehouse is already exists');
        $update = $stockLocation->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
