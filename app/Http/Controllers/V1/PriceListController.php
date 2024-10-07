<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListZone;
use App\Services\UserService;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    //

    private function priceListValidation(Request $req){
        return validator($req->all(),[
            'price' => 'nullable|numeric',
            'base_fee' => 'required|numeric',
            'below_kg' => 'nullable|numeric',
            'below_kg_price' => 'nullable|numeric',
            'above_kg' => 'nullable|numeric',
            'above_kg_price' => 'nullable|numeric',
            'delivery_type' => 'required|string|in:fast,normal',
            'apply_all_zones' => 'nullable|in:0,1',
            'zones' => 'nullable|array'
        ],[
            'delivery_type.in' => 'Delivery type must be of (Fast or Normal)'
        ]);
    }
    public function createPriceList(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->priceListValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $zones = $inputs['zones'] ?? null;
        unset($inputs['zones']);
        $create = PriceList::create($inputs);
        if(!$create) return ApiResponse::Error('Fail to create price list');
        if(!empty($zones)){
            foreach($zones as $zone){
                PriceListZone::create([
                    'zone_id' => $zone->id,
                    'price_list_id' => $create->id,
                ]);
            }
        }

        return ApiResponse::JsonResult(null,false,'Created');
    }

    public function getPriceList(Request $req){
        $user = UserService::getAuthUser();
        $query = PriceList::with(['zones'])->where(function($q){
            $q->where('is_deleted',0)->orWhere('status',1);
        })->where('company_id',$user->company_id);
        $priceList = $query->get();
        return ApiResponse::Pagination($priceList,$req,__('messages.get_price_list'));

    }

    public function getOnePriceList(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceList = PriceList::where(function($q){
            $q->where('is_deleted',0)->orWhere('status',1);
        })->where('company_id',$user->company_id)->find($id);
        if(!$priceList) return ApiResponse::NotFound(__('messages.not_found'));

        return ApiResponse::JsonResult($priceList,false,__('messages.get one price list'));
    }


    public function updatePriceList(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceList = PriceList::where(function($q){
            $q->where('is_deleted',0)->orWhere('status',1);
        })->where('company_id',$user->company_id)->find($id);
        if(!$priceList) return ApiResponse::NotFound(__('messages.not_found'));

        $validate = $this->priceListValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $zones = $inputs['zones'] ?? null;
        unset($inputs['zones']);
        $update = $priceList->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to update price list');
        if(!empty($zones)){
            foreach($zones as $zone){
                $exists = PriceListZone::where('price_list_id',$id)->where('zone_id',$zone['id'])->first();
                if($exists) continue;
                PriceListZone::create([
                    'zone_id' => $zone['id'],
                    'price_list_id' => $id,
                ]);
            }
        }
        return ApiResponse::JsonResult(null,false,'Updated');
    }

    public function voidPriceList(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceList = PriceList::where(function($q){
            $q->where('is_deleted',0)->orWhere('status',1);
        })->where('company_id',$user->company_id)->find($id);
        if(!$priceList) return ApiResponse::NotFound(__('messages.not_found'));
        $priceList->update([
            'status' => 0,
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);

        return ApiResponse::JsonResult(null,false,'Deleted');
    }
}
