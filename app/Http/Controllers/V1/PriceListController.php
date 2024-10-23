<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListname;
use App\Models\PriceListZone;
use App\Models\ProductType;
use App\Models\Zone;
use App\Services\UserService;
use DB;
use Exception;
use Illuminate\Http\Request;
use Log;

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
        return ApiResponse::JsonResult(null,'Created');
    }

    public function assignZoneToPriceList(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'price_list_name_id' => 'required|exists:price_list_names,id',
            'price_list_id' => 'nullable|int',
            'zones' => 'required|array'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $zoneIds = $inputs[ 'zones'];
        $priceListId = $inputs['price_list_id'] ?? null;
        $isUpdate = $priceListId ? true:false;
        $priceListName = PriceListname::where('is_deleted',0)->find($inputs['price_list_name_id']);
        if(!$priceListName) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Price List']));
        DB::beginTransaction();
        try{
            if(!$priceListId){
                $create = PriceList::create([
                    'price' => 0,
                    'price_list_name_id' => $inputs['price_list_name_id'],
                    'below_kg' => $priceListName->kg_marker,
                    'above_kg' => $priceListName->kg_marker,
                    'delivery_type' => 'normal',
                    'create_uid' => $user->id,
                    'update_uid' => $user->id,
                    'company_id' => $user->company_id,
                    'branch_id' => $user->branch_id,
                ]);
                $priceListId = $create->id;
            }
            $useIds = [];
            foreach($zoneIds as $idx=>$id){
                $existZone = Zone::where('is_deleted',0)->find($id);
                if(!$existZone) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Zone']).' at row '.($idx+1));
                $priceListZone = PriceListZone::where('price_list_id',$priceListId)->where('zone_id',$id)->first();
                $useIds[] = $id;
                if($priceListZone && $isUpdate) continue;
                PriceListZone::create([
                    'zone_id' => $id,
                    'price_list_id' => $priceListId,
                ]);
            }
            PriceListZone::where('price_list_id',$priceListId)->whereNotIn('zone_id',$zoneIds)->delete();
            // return $useIds;
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.assigned'));

        }catch(Exception $e){
            Log::error($e->getMessage());
            return ApiResponse::Error(__('messages.error',['info' => 'Fail to save']));
        }
    }

    public function createOrUpdatePriceList(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'id' => 'nullable|int',
            'delivery_type' => 'required|string',
            'base_fee' => 'nullable|numeric',
            'additional_fee' => 'nullable|numeric',
            'key' => 'required|in:below,above'
        ],[
            'key.in' => 'Key must be one of below,above'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $id = $inputs['id'] ?? null;
        $deliveryType = $inputs['delivery_type'];
        $baseFee = $inputs[ 'base_fee'] ?? 0;
        $insertOrUpdateArr = [
            'base_fee' => $baseFee,
        ];

        if($id){
            $priceList = PriceList::where('delivery_type',$deliveryType)->find($id);
            if(!$priceList) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Price List']));
            $priceList->update($insertOrUpdateArr);
        }
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
        return ApiResponse::JsonResult(null,'Updated');
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

        return ApiResponse::JsonResult(null,'Deleted');
    }
    /**
     * Summary of getPriceZones
     * @param \Illuminate\Http\Request $req
     * @return mixed|\Illuminate\Http\JsonResponse
     *
     */
    public function getPriceZones(Request $req){
        // $priceListNameId = $req->price_list_name_id ?? null;
        $arrObj = [
            [
                'title' => '1 Kg and Below',
                'kg_mark' => 1,
                'key' => 'below',
                'list' => []
            ],
            [
                'title' => 'Above 1 Kg',
                'kg_mark' => 1,
                'key' => 'above',
                'list' => []
            ]
        ];
        $priceList = PriceList::with(['zones'])->get();
        foreach ($priceList as $pl) {
            // $key = $pl->price_list_name_id;
            foreach ($arrObj as &$arr) {
                // Check if the key is either 'below' or 'above'
                if ($arr['key'] == 'below' || $arr['key'] == 'above') {
                    $zoneInfo = [];  // Reset zone names for each priceList
                    $zoneCodes = [];
                    foreach ($pl->zones as $z) {
                        $zoneInfo[] = (object)[
                            'code' => $z->zone_code,
                            'zone_id' => $z->id,
                            'name' => $z->zone_name
                        ];  // Collect zone names into an array
                        $zoneCodes[] = $z->zone_code;
                    }
                    // Join all zone names into a single string, separated by commas
                    $zonesString = implode(', ', $zoneCodes);
                    // Determine the additional_fee based on the key ('below' or 'above')
                    $additionalFee = ($arr['key'] == 'below') ? ($pl->below_kg_price ?? 0) : ($pl->above_kg_price ?? 0);
                    // Flag to track whether 'fast' and 'normal' delivery types are found
                    $fastFound = false;
                    $normalFound = false;

                    // Initialize the zone entry with a string of zone names and an empty delivery_types array
                    $zoneEntry = [
                        'zone_info' => $zoneInfo,  // Use the concatenated zone names string
                        'zone_codes' => $zonesString,
                        'delivery_types' => []  // Array for 'fast' and 'normal'
                    ];

                    // Set delivery types based on $pl data
                    if ($pl->delivery_type == 'fast') {
                        $fastFound = true;
                        $zoneEntry['delivery_types'][] = [
                            'id' => $pl->id,
                            'delivery_type' => 'fast',
                            'base_fee' => $pl->base_fee ?? 0,
                            'additional_fee' => $additionalFee  // Use the calculated additional fee
                        ];
                    }

                    if ($pl->delivery_type == 'normal') {
                        $normalFound = true;
                        $zoneEntry['delivery_types'][] = [
                            'id' => $pl->id,
                            'delivery_type' => 'normal',
                            'base_fee' => $pl->base_fee ?? 0,
                            'additional_fee' => $additionalFee  // Use the calculated additional fee
                        ];
                    }

                    // If 'fast' delivery type was not found, add a default entry for it
                    if (!$fastFound) {
                        $zoneEntry['delivery_types'][] = [
                            'id' => $pl->id,
                            'delivery_type' => 'fast',
                            'base_fee' => 0,
                            'additional_fee' => $additionalFee  // Use the calculated additional fee
                        ];
                    }

                    // If 'normal' delivery type was not found, add a default entry for it
                    if (!$normalFound) {
                        $zoneEntry['delivery_types'][] = [
                            'id' => $pl->id,
                            'delivery_type' => 'normal',
                            'base_fee' => 0,
                            'additional_fee' => $additionalFee  // Use the calculated additional fee
                        ];
                    }

                    // Add the zone entry with the concatenated zone names and delivery types to the list
                    $arr['list'][] = $zoneEntry;
                }
            }
        }
        return ApiResponse::JsonResult($arrObj,__('messages.Get List'));
    }
}
