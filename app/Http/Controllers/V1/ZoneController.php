<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PriceListZone;
use App\Models\User;
use App\Models\UserSubZone;
use App\Models\UserZone;
use App\Models\Zone;
use App\Services\UserService;
use DataResponse;
use DB;
use Exception;
use Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Log;

class ZoneController extends Controller
{
    //

    public function zoneValidation(Request $req){
        return validator($req->all(),[
            'zone_code' => 'nullable|string|max:30',
            'zone_name' => 'required|string|max:50',
            'zone_type' => 'required|string|max:30',
            'district' => "nullable|string|exists:districts,name",
            'commune' => "nullable|string|exists:communes,name",
            'city' => "required|string|exists:cities,name",
            'country_id' => "required|exists:countries,id",
            'description' => "nullable|string|max:250",
            'loc_lat' => 'nullable|numeric',
            'loc_lng' => 'nullable|numeric',
            'pin_map' => 'nullable|string',
            'parent_id' => 'nullable|int'
        ]);
    }

    public function createZone(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->zoneValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['identify'] = isset($inputs['parent_id']) ? 'child':'parent';
        $inputZoneCode = $inputs['zone_code'] ?? null;
        if($inputZoneCode){
            $existZone = Zone::where('is_deleted',0)->where('zone_code',$inputs['zone_code'])->first();
            if($existZone) return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'Zone code ('.$inputs['zone_code'].') is already exists.'
            ]));
        }
        $existZoneName = Zone::where('is_deleted',0)->where('zone_name',$inputs['zone_name'])->select('id','zone_name','zone_code')->first();
        if($existZoneName) return ApiResponse::Duplicated(__('messages.error',[
            'info' => 'Zone name ('.$inputs['zone_name'].'- '.$existZoneName->zone_code.') is already exists.'
        ]));
        // Log::info($inputs);
        $create = Zone::create($inputs);
        // if(!$create) return ApiResponse::Error('Fail to create zone');
        if(!$inputZoneCode) $create->update([
            'zone_code' => 'AZ'.$create->id
        ]);
        return ApiResponse::JsonResult(null,__('messages.created'));
    }

    public function getZones(Request $req){
        $user = UserService::getAuthUser();
        $search = $req->search;
        $query = Zone::query()->where('is_deleted',0)
        // ->where('company_id',$user->company_id)
        ->orderByDesc('id');
        if($search){
            $query->where(function($q) use($search){
                $q->where('zone_name','ilike','%'.$search.'%')->orWhere('zone_code','ilike','%'.$search.'%');
            });
        }
        $select = ['id','identity','zone_code','zone_type','parent_id','zone_name','commune','description','city','district','country_id','status'];
        $callback = function($zone){
            $zone->country_name = $zone->country->name;
            unset($zone->country);
            return $zone;
        };
        return ApiResponse::PaginationV1($query,$req,'Get Zones',[],500,$callback,$select);
    }

    public function getOneZone(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $zone = Zone::where(function($q){
            $q->where('is_deleted',0);
        })->where('company_id',$user->company_id)->selectRaw('id,zone_code,parent_id,zone_type,zone_name,commune,description,city,district,country_id,status')->find($id);
        if(!$zone) return ApiResponse::NotFound(__('messages.not_found'));
        return ApiResponse::JsonResult($zone,__('messages.get one'));
    }

    public function updateZone(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        // \Log::info($req->all());
        $zone = Zone::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$zone) return ApiResponse::NotFound(__('messages.not_found'));
        $validate = $this->zoneValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $existsType = Zone::where('is_deleted',0)->where('zone_code',$inputs['zone_code'])->where('id','!=',$id)->first();
        if($existsType) return ApiResponse::Duplicated(__('messages.error',[
            'info' => 'Zone code ('.$inputs['zone_code'].') is already exists.'
        ]));
        $existZoneName = Zone::where('is_deleted',0)->where('zone_name',$inputs['zone_name'])->where('id','!=',$id)->first();
        if($existZoneName) return ApiResponse::Duplicated(__('messages.error',[
            'info' => 'Zone name ('.$inputs['zone_name'].') is already exists.'
        ]));
        // $zone
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $zone->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to create zone');
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

    public function deleteZone(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        if($id == 300) return ApiResponse::ValidateFail('You cannot delete default zone!');
        $zone = Zone::where('is_deleted',0)->find($id);
        if(!$zone) return ApiResponse::NotFound(__('messages.not_found'));
        $deletedArr = [
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now(),
        ];
        $zone->update($deletedArr);
        PriceListZone::where('zone_id',$id)->update($deletedArr);
        return ApiResponse::JsonResult(null,__('messages.deleted'));
    }

    public function getZoneChildren(Request $req){
        $parentId = $req->id;
        $children = Zone::where('is_deleted',0)->where('parent_id',$parentId)->get();
        return ApiResponse::JsonResult($children);
    }

    public function assignZoneToParent(Request $req){
        $user = UserService::getAuthUser();
        $zoneId = $req->id;
        $zone = Zone::where('is_deleted',0)->find($zoneId);
        if(!$zone) return ApiResponse::NotFound();
        $childrenIds = $req->zones ?? null;
        if(empty($childrenIds) || !is_array($childrenIds)) {
            return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'Please select at least one zone.'
            ]));
        }
        $childrenZones = Zone::where('is_deleted', 0)
            ->with('parent:id,parent_id,zone_name')
            ->whereIn('id', $childrenIds)
            ->select(['id', 'update_uid', 'parent_id', 'identity'])
            ->get()
            ->keyBy('id');

        $validChildrenIds = [];

        $currentSubzones = Zone::where('parent_id', $zoneId)
            ->where('is_deleted', 0)
            ->pluck('id')
            ->toArray();

        // Step 2: Determine which subzones to detach
        $detachIds = array_diff($currentSubzones, $childrenIds);

        foreach ($childrenIds as $chId) {
            if (!isset($childrenZones[$chId])) {
                continue;
            }
            $zoneItem = $childrenZones[$chId];
            if (!empty($zoneItem->parent_id)) {
                // continue;
                if($zoneId == $zoneItem->parent_id){
                    continue;
                }
                return ApiResponse::Duplicated('This zone is already a sub zone of ('.$zoneItem->parent->zone_name.'), you cannot add it to another!');
            }

            if ($zoneId == $chId) {
                return ApiResponse::ValidateFail('It seems like you try to assign parent to itself!');
            }-

            $validChildrenIds[] = $chId;
        }

        if (!empty($validChildrenIds)) {
            Zone::whereIn('id', $validChildrenIds)
                ->where('is_deleted', 0)
                ->update([
                    'parent_id' => $zoneId,
                    'identity' => 'child',
                    'update_uid' => $user->id,
                ]);


            $zone->update([
                'update_id' => $user->id,
                'identity' => 'parent'
            ]);
        }
        if (!empty($detachIds)) {
            Zone::whereIn('id', $detachIds)
                ->update([
                    'parent_id' => null,
                    'identity' => 'parent',
                    'update_uid' => $user->id,
                ]);
        }

        return ApiResponse::JsonResult(null,'Assigned');
    }

    public function assignZoneToDriver(Request $req){
        $user = UserService::getAuthUser();
        $validator = validator($req->all(),[
            'driver_id' => 'required|int',
            'zones' => 'required|array'
        ]);
        if($validator->fails()) return ApiResponse::ValidateFail($validator->errors()->first());
        $inputs = $validator->validated();
        $assignZones = $inputs['zones'];
        $driver = User::where('is_deleted',0)
        ->where('account_type','driver')
        ->select('id')
        ->find($inputs['driver_id']);
        if(!$driver) return ApiResponse::NotFound();
        $zoneIds = Helper::pluckArrValue($assignZones,'zone_id');
        $zones = Zone::whereIn('id',$zoneIds)
        ->select(['id','parent_id','identity','zone_name','zone_code'])
        ->get()->keyBy('id');
        $subZones = Zone::where('is_deleted',0)->whereIn('parent_id',$zoneIds)
        ->select('id')->get()->keyBy('id');
        // get SubZone id
        $clSubZOne = clone $subZones;
        $subZoneIds = $clSubZOne->keys();
        // get existing user zones
        $userZones = UserZone::whereIn('zone_id',$zoneIds)
        ->select('id','create_uid','zone_id')
        ->get()->keyBy('zone_id');
        $userSubZones = UserSubZone::whereIn('zone_id',$subZoneIds)
        ->select('id','create_uid','zone_id')
        ->get()->keyBy('zone_id');
        // return $userSubZones;
        // $remainingSubZoneIds = array_column($subZones, 'zone_id');
        // return $remainingSubZoneIds;
        $setDriverZoneArr = [];
        $setDriverSubZone = [];
        $branchId = $user->branch_id;
        $userId = $user->id;
        $companyId = $user->id;
        $extraFields = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'is_deleted' => false,
            'update_uid' => $userId
        ];
        foreach($assignZones as $zone){
            $zId = $zone['zone_id'];
            if(!isset($zones[$zId])){
                continue;
            }
            $parentZone = $zones[$zId] ?? null;
            $parentName = $parentZone->zone_name.'('.$parentZone->zone_code.')';
            if(!$parentZone) return ApiResponse::NotFound();
            if($parentZone->identity != 'parent'){
                return ApiResponse::ValidateFail('please select parent zone, '.$parentName.' is the sub zone!');
            }
            $extraFields['parent_id'] = $zId;
            $callbackChildren = $this->validChildZones($zone['children'],$subZones,$userSubZones,$extraFields,$parentName);
            if($callbackChildren->error){
                return ApiResponse::flex($callbackChildren);
            }
            $setDriverSubZone = $callbackChildren->data['children'];
            $zoneData = $userZones[$zId] ?? null;
            $setData = [
                'user_id' => $inputs['driver_id'],
                'zone_id' => $zId,
                'is_deleted' => false,
                'create_uid' => $zoneData?->create_uid ?? $user->id,
                'update_uid' => $userId,
                'branch_id' => $branchId,
                'company_id' => $companyId,
            ];
            if($zoneData){
                $setData['id'] = $zoneData->id;
            }
            $setDriverZoneArr[] = $setData;
        }
        // return $setDriverZoneArr;
        // return $setDriverSubZone;
        // // return $updateSubZoneArr;
        DB::transaction(function () use (&$setDriverZoneArr, &$setDriverSubZone) {
            // Step 1: Upsert UserZone first
            UserZone::upsert($setDriverZoneArr, ['id'], [
                'zone_id',
                'is_deleted',
                'create_uid',
                'update_uid',
                'branch_id',
                'company_id',
            ]);

            // Step 2: Get latest UserZone records to map zone_id → id
            $zoneIds = array_column($setDriverZoneArr, 'zone_id');
            $userZoneMap = UserZone::whereIn('zone_id', $zoneIds)
                ->select('id', 'zone_id')
                ->get()
                ->keyBy('zone_id');
            // Log::info($userZoneMap);
            // Step 3: Update user_zone_id in $setDriverSubZone
            $toInsert = [];
            $toUpdate = [];
            foreach ($setDriverSubZone as &$row) {
                $parentId = $row['parent_id'];
                unset($row['parent_id']);
                if (isset($userZoneMap[$parentId])) {
                    // Log::info('Found zone_id in userZoneMap');
                    $row['user_zone_id'] = $userZoneMap[$parentId]->id ?? null;
                    if (!empty($row['id'])) {
                        $toUpdate[] = $row;
                    } else {
                        unset($row['id']); // Make sure id is not passed
                        $toInsert[] = $row;
                    }
                } else {
                    Log::info("zone_id $parentId not found in userZoneMap");
                }

            }

            if (!empty($toInsert)) {
                UserSubZone::insert($toInsert);
            }

            // update existing
            if (!empty($toUpdate)) {
                UserSubZone::upsert($toUpdate, ['id'], [
                    'user_zone_id',
                    'zone_id',
                    'is_deleted',
                    'create_uid',
                    'update_uid',
                    'branch_id',
                    'company_id',
                ]);
            }
            // throw new Exception('Success Exception');
        });
        return ApiResponse::JsonResult(null,'Assigned');
    }

    private function validChildZones(array $children,Collection $subZones,Collection $userSubZones,array $extraFields,string $parentName){
        $validChildren = [];
        foreach ($children as $idx => $childId) {
            if (!isset($subZones[$childId])) {
                return DataResponse::ValidateFail(__('messages.info', [
                    'info' => 'Sub Zone row('.($idx + 1).') not found by '.$parentName
                ]));
            }
            $existingSubZone = $userSubZones[$childId] ?? null;
            $setData = [
                'zone_id' => $childId,
                'create_uid' => $existingSubZone?->create_uid ?? $extraFields['update_uid'],
            ];
            if($existingSubZone) $extraFields['id'] = $existingSubZone?->id;
            else $extraFields['id'] = null;
            $validChildren[] = array_merge(
                $setData,
                $extraFields
            );
            unset($extraFields['id']);
        }
        $removedChildIds = array_diff($userSubZones->keys()->all(), $children);
        foreach ($removedChildIds as $removedZoneId) {
            $existing = $userSubZones[$removedZoneId];
            // Log::info($existing);
            $validChildren[] = array_merge(
                [
                    'id' => $existing->id,
                    'zone_id' => $existing->zone_id,
                    'create_uid' => $existing->create_uid,
                ],
                $extraFields,
                ['is_deleted' => true] // override
            );
        }
        // Log::info($removedChildIds);
        return DataResponse::JsonResult([
            'children' => $validChildren,
        ]);
    }
}
