<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Services\UserService;
use Helper;
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
            'use_branch_id' => 'nullable|int|exists:branches,id',
            'main' => 'nullable|in:0,1',
            'inactive' => 'nullable|in:0,1'
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
        $isMain = $inputs['main'] ?? false;
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
        $rows = StockLocation::with('type:id,name')->where('branch_id',$user->branch_id)->selectRaw('id,name,inactive,address,main,type_id,use_branch_id')->get();
        foreach($rows as $row){
            $row->warehouse_type = $row->type->name;
            if($row->inactive) $row->status = 'Inactive';
            else $row->status = 'Active';
            if($row->main){
                $row->warehouse_type = $row->warehouse_type.' (Main)';
            }else $row->warehouse_type = $row->warehouse_type.' (Local Shop)';
            unset($row->type);
        }
        return ApiResponse::JsonResult($rows);
    }

    public function getStockLocation(Request $req,$id){
        $user = UserService::getAuthUser();
        $rows = StockLocation::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($rows);
    }

    public function getStockItemByWarehouse(Request $req){
        $id = $req->id;
        $stockItems = Stock::with(['variant.product','stockLocation.type','variant.photos'])->where('stock_location_id',$id)->get();
        foreach($stockItems as $item){
            $item->product_name = $item->variant->product->name;
            $item->size = $item->variant->size;
            $item->product_name = $item->variant->product->name;
            $item->product_description = $item->variant->product->description;
            $item->color = $item->variant->color;
            $item->expires_at = $item->variant->expires_at;
            $item->condition = $item->variant->condition;
            $item->product_code = $item->variant->product->code;
            $item->product_details = 'Color: '.$item->color.', Size: '.$item->size.', Condition: '.$item->condition;
            $item->retail_price = $item->retail_price > 0 ? $item->retail_price : $item->variant->retail_price;
            $item->warehouse = $item->stockLocation->name.($item->stockLocation->main ? ' (Main Warehouse)':' (Branch Shop)');
            foreach($item->variant->photos as $photo){
                if($photo->is_thumbnail){
                    $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
                }
                if($item->image_url) $item->image_url = Helper::getImageUrl($photo->photo_file_name,$item->company_id,$photo->directory);
            }
            unset($item->stockLocation,$item->variant);
        }
        return ApiResponse::Pagination($stockItems,$req);
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
        $isMain = isset($inputs['main']) ? $inputs['main'] : false;
        if($isMain) {
            $hasMain = StockLocation::where('company_id',$user->company_id)->where('id','!=',$id)->take(1)->value('main');
            if($hasMain) return ApiResponse::Duplicated('The main wareharehouse is already exists');
        }
        $update = $stockLocation->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
