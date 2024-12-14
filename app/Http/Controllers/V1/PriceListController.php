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
            'price_list_name_id' => 'required|int',
            'key' => 'required|string',
            'base_fee' => 'required|numeric',
            'below_kg' => 'nullable|numeric',
            'below_kg_price' => 'nullable|numeric',
            'above_kg' => 'nullable|numeric',
            'above_kg_price' => 'nullable|numeric',
            'delivery_type' => 'required|string|in:fast,normal',
            'additional_fee' => 'nullable|numeric',
            'identifier' => 'nullable|string',
            'apply_all_zones' => 'nullable|in:0,1',
            'zones' => 'nullable|array'
        ],[
            'delivery_type.in' => 'Delivery type must be of (Fast or Normal)'
        ]);
    }
    // public function createPriceList(Request $req){
    //     $user = UserService::getAuthUser();
    //     $validate = $this->priceListValidation($req);
    //     if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
    //     $inputs = $validate->validated();
    //     $inputs['create_uid'] = $user->id;
    //     $inputs['update_uid'] = $user->id;
    //     $inputs['company_id'] = $user->company_id;
    //     $inputs['branch_id'] = $user->branch_id;
    //     $zones = $inputs['zones'] ?? null;
    //     unset($inputs['zones']);
    //     $create = PriceList::create($inputs);
    //     if(!$create) return ApiResponse::Error('Fail to create price list');
    //     if(!empty($zones)){
    //         foreach($zones as $zone){
    //             PriceListZone::create([
    //                 'zone_id' => $zone->id,
    //                 'price_list_id' => $create->id,
    //                 'base_fee' => $inputs['base_fee'] ?? 0,
    //                 'additional_fee' => $inputs['additional_fee'] ?? 0
    //             ]);
    //         }
    //     }
    //     return ApiResponse::JsonResult(null,'Created');
    // }

    public function assignZoneToPriceList(Request $req){
        $user = UserService::getAuthUser();
        $validate = validator($req->all(),[
            'price_list_name_id' => 'required|exists:price_list_names,id',
            'price_list_id' => 'nullable|int',
            'identifier' => 'nullable|string',
            'zones' => 'required|array'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $zoneIds = $inputs[ 'zones'];
        $priceListNameId = $inputs['price_list_name_id'];
        $priceListName = PriceListname::where('is_deleted',0)->find($priceListNameId);
        if(!$priceListName) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Price List']));
        // $priceList = PriceList::where('price_list_name_id',$priceListNameId)->first();
        $priceListId = $inputs['price_list_id'] ?? null;
        // $isUpdate = $priceListId ? true:false;
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
            // $useIds = [];
            $uniqueKeys = $inputs['identifier'] ?? uniqid('PZ');
            foreach($zoneIds as $idx=>$id){
                $existZone = Zone::where('is_deleted',0)->find($id);
                if(!$existZone) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Zone']).' at row '.($idx+1));
                $priceListZone = PriceListZone::where('price_list_id',$priceListId)->where('identifier',$uniqueKeys)->where('zone_id',$id)->first();
                // if($priceListId) $useIds[] = $id;
                if(!$priceListZone) {
                    PriceListZone::create([
                        'zone_id' => $id,
                        'price_list_id' => $priceListId,
                        'identifier' => $uniqueKeys,
                        'base_fee' => $priceList?->base_fee ?? 0,
                        'additional_fee' => $priceList?->additional_fee ?? 0
                ]);
                }
                // return ApiResponse::Duplicated(__('messages.info',[
                //     'info' => 'Zone '.$existZone->zone_name."($existZone->zone_code) has already assigned you cannot reassign"
                // ]));

            }
            PriceListZone::where('price_list_id',$priceListId)->where('identifier',$uniqueKeys)->whereNotIn('zone_id',$zoneIds)->delete();
            // return $useIds;
            // return PriceListZone::get();
            DB::commit();
            return ApiResponse::JsonResult(null,__('messages.assigned'));

        }catch(Exception $e){
            Log::error($e->getMessage());
            return ApiResponse::Error(__('messages.error',['info' => 'Fail to save']));
        }
    }

    // public function createOrUpdatePriceList(Request $req){
    //     $user = UserService::getAuthUser();
    //     $validate = validator($req->all(),[
    //         'id' => 'nullable|int',
    //         'delivery_type' => 'required|string',
    //         'base_fee' => 'nullable|numeric',
    //         'additional_fee' => 'nullable|numeric',
    //         'key' => 'required|in:below,above'
    //     ],[
    //         'key.in' => 'Key must be one of below,above'
    //     ]);
    //     if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
    //     $inputs = $validate->validated();
    //     $id = $inputs['id'] ?? null;
    //     $deliveryType = $inputs['delivery_type'];
    //     $baseFee = $inputs[ 'base_fee'] ?? 0;
    //     $insertOrUpdateArr = [
    //         'base_fee' => $baseFee,
    //     ];

    //     if($id){
    //         $priceList = PriceList::where('delivery_type',$deliveryType)->find($id);
    //         if(!$priceList) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Price List']));
    //         $priceList->update($insertOrUpdateArr);
    //     }else{
    //         PriceList::create($inputs);
    //     }
    // }


    public function updatePriceList(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id ?? null;
        $validate = $this->priceListValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $zones = $inputs['zones'] ?? null;
        $key = $inputs['key'];
        $deliveryType = $inputs['delivery_type'];
        $priceListNameId = $inputs['price_list_name_id'];
        $priceListName = PriceListname::where('is_deleted',0)->find($priceListNameId);
        if(!$priceListName) return ApiResponse::NotFound();
        $priceList = PriceList::where(function($q){
            $q->where('is_deleted',0)->orWhere('status',1);
        })->where('price_list_name_id',$priceListNameId)->where('company_id',$user->company_id)->find($id);
        if(!$priceList || !$id) {
            $newPriceList = PriceList::create([
                'delivery_type' => $deliveryType,
                'base_fee' => $inputs['base_fee'],
                'additional_fee' => $inputs['additional_fee'],
                'below_kg' => $priceListName->kg_marker,
                'below_kg_price' => $inputs['additional_fee'] ?? 0,
                'price_list_name_id' => $priceListNameId,
                'above_kg' => $priceListName->kg_marker,
                'above_kg_price' => $inputs['additional_fee'] ?? 0,
                'create_uid' => $user->id,
                'update_uid' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);
            $id = $newPriceList->id;
        }else{
            if($key == 'below') $inputs['below_kg_price'] = $inputs['additional_fee'];
            else if ($key == 'above') $inputs['above_kg_price'] = $inputs['additional_fee'];
            unset($inputs['delivery_type']);
            $priceList->update($inputs);
        }
        unset($inputs['zones']);
        $uniqueKeys = $inputs['identifier'] ?? uniqid('PZ');
        if(!empty($zones)){
            foreach($zones as $zoneId){
                if (!$uniqueKeys) {
                    $uniqueKeys = uniqid('PZ');
                }
                $exists = PriceListZone::where('price_list_id',$id)->where('identifier',$uniqueKeys)->where('zone_id',$zoneId)->first();
                if(!$exists) {
                    // $uniqueKeys = uniqid('PZ');
                    PriceListZone::create([
                        'zone_id' => $zoneId,
                        'price_list_id' => $id,
                        'identifier' => $uniqueKeys
                        // 'base_fee' => $inputs['base_fee'] ?? 0,
                        // 'additional_fee' => $inputs['additional_fee'] ?? 0
                    ]);
                }
                // else{
                    // $exists->update([
                    //     'zone_id' => $zoneId,
                    //     'price_list_id' => $id,
                    //     'identifier' => $uniqueKeys
                    // ]);
                // }
            }
            PriceListZone::where('price_list_id',$id)->where('identifier',$uniqueKeys)->whereNotIn('zone_id',$zones)->delete();
        }
        return ApiResponse::JsonResult(null,'Updated');
    }

    public function deleteZoneFromPriceList(){

    }

    private function checkValidPriceList($type,$priceListNameId): bool{
        $priceList = PriceList::where('delivery_type',$type)
        ->where('price_list_name_id',$priceListNameId)
        ->where('is_deleted',0)->take(1)->value('id');
        return $priceList ? true:false;
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


    private function getZoneInfo($zones,$identifier,$priceListId){
        $zoneCodes = [];
        foreach($zones as $z){
            if($z->identifier == $identifier && $z->price_list_id == $priceListId){
                $zoneCodes[] = (object)[
                    'zone_name' => $z->zone->zone_name,
                    'code' => $z->zone->zone_code,
                    'id' => $z->zone->id
                ];
            }
        }
        return $zoneCodes;
    }

    private function getPriceListInfo($list,$plId,$type){
        foreach($list as $ls){
            if($ls->id == $plId && $ls->delivery_type == $type){
                // var_dump($ls->id);
                return $ls;
            }
        }
    }
    /**
     * Summary of getPriceZones
     * @param \Illuminate\Http\Request $req
     * @return mixed|\Illuminate\Http\JsonResponse
     *
     */
    public function getPriceZones(Request $req){
        $priceListNameId = $req->price_list_name_id ?? null;
        $priceListName = PriceListname::where('is_deleted',0)->selectRaw('id,name,kg_marker')->find($priceListNameId);
        if(!$priceListName) return ApiResponse::NotFound();

        $priceList = PriceList::where('price_list_name_id',$priceListNameId)
        ->selectRaw('base_fee,below_kg,below_kg_price,above_kg,above_kg_price,delivery_type,id')
        ->orderByRaw("delivery_type = 'normal' DESC")->get();
        $pZ = PriceListZone::whereHas('priceList',function($q) use($priceListNameId){
            $q->where('price_list_name_id',$priceListNameId);
        });
        $pzClone = clone $pZ;
        $pzList = $pzClone->selectRaw('price_list_id,identifier,zone_id')
        ->with('zone')->get();
        // $priceZones = $pZ->with(['priceList:id,delivery_type'])->selectRaw('price_list_id,identifier')
        //     ->groupBy('price_list_id', 'identifier')
        //     ->orderByRaw('price_list_id')
        //     ->get()
        //     ->toArray();
        $priceZones = $pZ->with(['priceList:id,delivery_type'])
        ->selectRaw('price_list_id, identifier')
        ->join('price_list', 'price_list_zones.price_list_id', '=', 'price_list.id') // Assuming 'price_list_zones' is the table name
        ->groupBy('price_list_zones.price_list_id', 'price_list_zones.identifier', 'price_list.delivery_type')
        ->orderByRaw('price_list.delivery_type = \'normal\' DESC')
        ->get()
        ->toArray();

        $arrObj = [
            [
                'title' => $priceListName->kg_marker.' Kg and Below',
                'kg_mark' => $priceListName->kg_marker,
                'key' => 'below',
                'list' => []
            ],
            [
                'title' => 'Above '.$priceListName->kg_marker.' Kg',
                'kg_mark' => $priceListName->kg_marker,
                'key' => 'above',
                'list' => []
            ]
        ];

        foreach ($arrObj as &$arr) {
            if ($arr['key'] === 'below' || $arr['key'] === 'above') {
                // Group priceZones by identifier
                $groupedPriceZones = [];
                $zoneIds = [];
                foreach ($priceZones as &$z) {
                    // Fetch price list info for the given price list id and delivery type
                    $plInfo = $this->getPriceListInfo($priceList, $z['price_list_id'], $z['price_list']['delivery_type']);

                    // Group priceZones by identifier

                    if (!isset($groupedPriceZones[$z['identifier']])) {
                        $zInfo = $this->getZoneInfo($pzList, $z['identifier'], $z['price_list_id']);
                        if ($zInfo) {
                            // Use array_column to extract 'id' if $zInfo is an array of associative arrays
                            $zoneIds = array_merge($zoneIds, array_column($zInfo, 'id'));
                        }
                        $groupedPriceZones[$z['identifier']] = [
                            'identifier' => $z['identifier'],
                            'zone_info' => $zInfo,
                            'delivery_types' => []
                        ];
                    }

                    // Add a new entry for the current $plInfo if not already present
                    $groupedPriceZones[$z['identifier']]['delivery_types'][] = [
                        'id' => $plInfo->id,
                        'price_list_id' => $plInfo->id,
                        'delivery_type' => $plInfo->delivery_type,
                        'base_fee' => $plInfo->base_fee ?? 0,
                        'additional_fee' => $arr['key'] === 'below' ? $plInfo->below_kg_price : $plInfo->above_kg_price,
                        'zone_ids' => $zoneIds,
                        'type' => $arr['key'],
                    ];
                }

                // Process each grouped price zone
                foreach ($groupedPriceZones as &$zone) {
                    $existingDeliveryTypes = array_column($zone['delivery_types'], 'delivery_type');
                    $entriesWithId = [];

                    // Remove entries with `id = null` if there are valid entries for the same delivery type
                    foreach ($zone['delivery_types'] as $entry) {
                        if ($entry['id'] !== null) {
                            $entriesWithId[$entry['delivery_type']] = $entry;
                        }
                    }

                    // Replace delivery types with valid entries
                    $zone['delivery_types'] = array_values($entriesWithId);

                    // Add missing delivery types
                    $requiredTypes = ['normal', 'fast'];
                    foreach ($requiredTypes as $type) {
                        if (!in_array($type, array_column($zone['delivery_types'], 'delivery_type'))) {
                            $zone['delivery_types'][] = [
                                'id' => null,
                                'price_list_id' => null,
                                'delivery_type' => $type,
                                'base_fee' => 0,
                                'additional_fee' => 0,
                                'zone_ids' => [],
                                'type' => $arr['key'],
                            ];
                        }
                    }

                    // Ensure 'normal' is the first entry
                    usort($zone['delivery_types'], function ($a, $b) {
                        return $a['delivery_type'] === 'normal' ? -1 : ($b['delivery_type'] === 'normal' ? 1 : 0);
                    });
                }

                // Flatten the grouped price zones into the list
                $arr['list'] = array_values($groupedPriceZones);
            }
        }




        // foreach ($arrObj as &$arr) {
        //     if ($arr['key'] === 'below' || $arr['key'] === 'above') {
        //         // Group priceZones by identifier
        //         $groupedPriceZones = [];
        //         foreach ($priceZones as &$z) {
        //             // Fetch price list info for the given price list id and delivery type
        //             $plInfo = $this->getPriceListInfo($priceList, $z['price_list_id'], $z['price_list']['delivery_type']);

        //             // Group priceZones by identifier
        //             if (!isset($groupedPriceZones[$z['identifier']])) {
        //                 $groupedPriceZones[$z['identifier']] = [
        //                     'identifier' => $z['identifier'],
        //                     'zone_info' => $this->getZoneInfo($pzList,$z['identifier'],$z['price_list_id']),
        //                     'delivery_types' => []
        //                 ];
        //             }


        //             // Add delivery types for the given zone, but avoid duplicates
        //             foreach (['normal', 'fast'] as $type) {
        //                 // Check if this price_list_id and delivery_type combination already exists
        //                 $existingKey = array_search($plInfo->id, array_column($groupedPriceZones[$z['identifier']]['delivery_types'], 'price_list_id'));

        //                 if ($existingKey === false) {
        //                     // If no entry exists for this price_list_id, add it
        //                     $groupedPriceZones[$z['identifier']]['delivery_types'][] = [
        //                         'id' => $plInfo->id,
        //                         'price_list_id' => $plInfo->id,
        //                         'delivery_type' => $plInfo->delivery_type ?? $type,
        //                         'base_fee' => $plInfo->base_fee ?? 0,
        //                         'additional_fee' => $arr['key'] === 'below' ? $plInfo->below_kg_price : $plInfo->above_kg_price,
        //                         'type' => $arr['key'],
        //                     ];
        //                 } else {
        //                     // Check if this price_list_id exists but with a different delivery_type
        //                     $existingDeliveryType = $groupedPriceZones[$z['identifier']]['delivery_types'][$existingKey]['delivery_type'];

        //                     // If the delivery type is different, add the new entry
        //                     if ($existingDeliveryType !== $type) {
        //                         $groupedPriceZones[$z['identifier']]['delivery_types'][] = [
        //                             'id' => null,
        //                             'price_list_id' => null,
        //                             'delivery_type' => $type,
        //                             'base_fee' => 0,
        //                             'additional_fee' => 0,
        //                             'type' => $arr['key'],
        //                         ];
        //                     }
        //                 }
        //             }
        //         }

        //         // Flatten the grouped price zones into the list
        //         $arr['list'] = array_values($groupedPriceZones);
        //     }
        // }

        return ApiResponse::JsonResult($arrObj,__('messages.Get List'));
    }
}
