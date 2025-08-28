<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MerchantPriceList;
use App\Models\User;
use App\Models\Zone;
use App\Services\GeneralSettingService;
use App\Services\UserService;
use DB;
use Helper;
use Illuminate\Http\Request;
class MerchantManagementController extends Controller
{
    //
    protected $userClass = 'merchant';
    public function createMerchant(Request $req){
        $user = UserService::getAuthUser();
        $createMerchant = UserService::createOrUpdateUser($req,$this->userClass,$user);
        return ApiResponse::flex($createMerchant);
    }

    public function getMerchants(Request $req){
        $user = UserService::getAuthUser();
        $statusId = $req->status_id ?? null;
        $branchId = $req->branch_id ?? null;
        $search = $req->search;
        $priceList = DB::table('price_list_names as n')
        ->selectRaw('n.id,n.name,mpl.merchant_id,mpl.zone_code,mpl.zone_id')->join('merchant_price_list as mpl','mpl.price_list_id','n.id')
        ->get();
        $query = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type',$this->userClass)
        ->with(['merchantType:id,name_en as names','bank_accounts:id,currency,bank_name,bank_number,account_name,user_id,is_primary','shop:id,owner_id,name_en as shop_name_en,name_km as shop_name_km,product_type_id,city,district,est_pcs','shop.product_type:id,name']);
        // ->selectRaw('id,cod_fee,code,name_km,username,email,gender,business_type,phone,client_type_id,address,cod,pin_address,photo_file_name,lock,has_account,photo_file_name');
        // ->select(['id','cod_fee','code','name_km','username','email','name_en',''])
        if($statusId !== null && $statusId>=0) {
            $query->where('lock',$statusId ? 0 : 1);
        }
        if($branchId){
            $query->where('branch_id',$branchId);
        }

        if($search){
            $query->where(function($q) use ($search){
                $q->where('code','ilike','%'.$search.'%')
                ->orWhere('username','ilike','%'.$search.'%')
                ->orWhere('name_km','ilike','%'.$search.'%')
                ->orWhere('phone','ilike','%'.$search.'%');
            });
        }
        $query->orderByDesc('id');
        // foreach($merhcants as $m){

        //     $merchantPriceList = $this->getMerchantPriceList($priceList,$m->id);
        //     $m->referrer = null;
        //     $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
        //     $m->price_list_name = $merchantPriceList?->name;
        //     $m->price_list_id = $merchantPriceList?->id;
        //     $m->zone_id = $merchantPriceList?->zone_id;
        //     $m->client_type = $m->merchantType?->name;
        //     $m->login_name = $m->login_name ?? $m->phone;
        //     $m->create_by = ($m->create_uid == $m->id) ? 'Self': 'Admin';
        //     foreach($m->bank_accounts as $b){
        //         if($b->is_primary) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
        //         if(!$b->bank_account) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
        //     }
        //     $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
        //     unset($m->merchantType,$m->bank_accounts,$m->photo_file_name);
        // }
        $callback = function($m) use($priceList,$user){
            $merchantPriceList = $this->getMerchantPriceList($priceList,$m->id);
            $m->referrer = null;
            $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
            $m->price_list_name = $merchantPriceList?->name;
            $m->price_list_id = $merchantPriceList?->id;
            $m->zone_id = $merchantPriceList?->zone_id;
            $m->client_type = $m->merchantType?->name;
            $m->login_name = $m->login_name ?? $m->phone;
            $m->create_by = ($m->create_uid == $m->id) ? 'Self': 'Admin';
            $m->shop_name_en = $m->shop?->shop_name_en;
            $m->shop_name_km = $m->shop?->shop_name_km;
            $m->city = $m->shop?->city;
            $m->district = $m->shop?->district;
            $m->est_pcs = $m->shop?->est_pcs;
            $m->product_type = $m->shop?->product_type?->name;
            unset($m->shop);
            foreach($m->bank_accounts as $b){
                if($b->is_primary) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                if(!$b->bank_account) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
            }
            $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
            unset($m->merchantType,$m->bank_accounts,$m->photo_file_name);
            return $m;
        };
        return ApiResponse::PaginationV1($query,$req,'',[],1000,$callback);
    }

    public function getDefaultOptions(Request $req){
        $id = $req->id;
        $merchant = User::where('is_deleted',0)->with('merchantPriceList')->where('account_type','merchant')->selectRaw('id,cod,cod_fee')->where('id',$id)->first();
        if(!$merchant) return ApiResponse::NotFound();
        $merchant->cod = $merchant->cod ? "1":"0";
        $merchant->zone_code = $merchant->merchantPriceList?->zone_code;
        $merchant->zone_id = $merchant->merchantPriceList?->zone_id;
        $merchant->price_list_id = $merchant->merchantPriceList?->price_list_id;
        unset($merchant->merchantPriceList);
        return ApiResponse::JsonResult($merchant);
    }
    // public static function concatBankInfo($bankName,$bankNumber,$accountName){
    //     $info = $bankName;
    //     if($bankNumber) $info .= '|'.$bankNumber;
    //     if($accountName) $info .= '|'.$accountName;
    //     return $info;
    // }
    public function getOneMerchant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $merchant = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type',$this->userClass)
        ->with(['bank_accounts:id,bank_name,bank_number,currency,account_name,user_id,is_primary','shops:id,owner_id,name_en as shop_name_en,name_km as shop_name_km,phone,est_pcs,address,city,district,commune,product_type_id'])
        ->selectRaw('id,cod_fee,code,branch_id,name_km,username,email,gender,photo_file_name,business_type,phone,client_type_id,address,referrer_uid,cod,pin_address,login_name')
        ->find($id);
        $priceList = DB::table('price_list_names as n')
        ->selectRaw('n.id,n.name,mpl.merchant_id')
        ->join('merchant_price_list as mpl','mpl.price_list_id','n.id')
        ->where('mpl.merchant_id',$id)->first();
        if(!$merchant) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Merchant']));
        $merchant->image_url = Helper::getImageUrl($merchant->photo_file_name,$user->company_id,'user_profile');
        $merchant->login_name = $merchant->login_name ?? $merchant->phone;
        if($priceList){
            $merchant->price_list_id = $priceList->id;

        }
        $cod = $merchant->cod;
        $merchant->cod = $cod ? "1":"0";
        unset($m->merchantType,$m->bank_accounts);
        return ApiResponse::JsonResult($merchant,__('messages.get one'));
    }

    private function getMerchantPriceList($rows,$merchantId){
        foreach($rows as $row){
            if($row->merchant_id == $merchantId){
                return $row;
            }
        }
        return null;
    }

    public function getMerchantListByDate(Request $req){
        $user = UserService::getAuthUser();
        $startDate = $req->startDate ? Helper::dateDMY($req->startDate) : null;
        $endDate = $req->endDate ? Helper::dateDMY($req->endDate) : null;
        if(!$startDate || !$endDate) return ApiResponse::ValidateFail('Please select a date range to view this report');
        $query = User::from('users')->join('packages', 'users.id', '=', 'packages.merchant_id')
        ->where('packages.is_deleted',0)
        ->where('packages.outstanding',0)
        ->where('users.company_id', $user->company_id)
        ->where('users.account_type', 'merchant')
        ->where('users.is_deleted',0)
        // ->selectRaw('DISTINCT users.id, users.username, users.name_km, users.phone')
        ->distinct()
        ->orderByDesc('users.id');
        if($startDate && $endDate){
            $startDatetime = Helper::dateYMD($startDate).' 00:00:00';
            $endDatetime = Helper::dateYMD($endDate).' 23:59:59';
            $query->where(function ($q) use ($startDatetime, $endDatetime) {
                $q->where(function ($q) use ($startDatetime, $endDatetime) {
                    $q->where(function ($q) use ($startDatetime, $endDatetime) {
                        $q->where('status_id', 5)
                          ->whereBetween('arrive_warehouse_datetime', [$startDatetime, $endDatetime]);
                    })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                        $q->where('status_id', 6)
                          ->whereBetween('assign_driver_datetime', [$startDatetime, $endDatetime]);
                    })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                        $q->where('status_id', 10)
                          ->whereBetween('failed_datetime', [$startDatetime, $endDatetime]);
                    })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                        $q->where('status_id', 19)
                          ->whereBetween('failed_datetime', [$startDatetime, $endDatetime]);
                    })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                        $q->where('status_id', 9)
                          ->whereBetween('delivered_datetime', [$startDatetime, $endDatetime]);
                    })->orWhere(function ($q) use ($startDatetime, $endDatetime) {
                        $q->where('status_id', 11)
                          ->whereBetween('returned_datetime', [$startDatetime, $endDatetime]);
                    });
                });

                // $q->whereRaw(
                //     '(packages.status_id = 5 AND packages.arrive_warehouse_datetime BETWEEN ? AND ?)
                //     OR (packages.status_id = 6 AND packages.assign_driver_datetime BETWEEN ? AND ?)
                //     OR (packages.status_id = 10 AND packages.failed_datetime BETWEEN ? AND ?)
                //     OR (packages.status_id = 19 AND packages.failed_datetime BETWEEN ? AND ?)
                //     OR (packages.status_id = 9 AND packages.delivered_datetime BETWEEN ? AND ?)
                //     OR (packages.status_id = 11 AND packages.returned_datetime BETWEEN ? AND ?)',
                //     [$startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime, $startDatetime, $endDatetime]
                // );
            });
        }

        $select = ['users.id', 'users.username', 'users.name_km', 'users.phone',"users.*"];
        $callback = function ($q){
            return $q;
        };

        return ApiResponse::PaginationV1($query,$req, 'Get Merchant List By Date',[],1000,$callback,$select);
    }

    public function updateMerchant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $updateMerchant = UserService::createOrUpdateUser($req,$this->userClass,$user,$id);
        return ApiResponse::flex($updateMerchant);
    }


    public function createMerchantAccount(Request $req){
        $user = UserService::getAuthUser();
        $merchantId = $req->id;
        $createMerchant = UserService::createLoginAccount($req,$merchantId,'merchant',$user);
        return ApiResponse::flex($createMerchant);
    }

    public function setMerchantPriceList(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $priceListId = $req->price_list_id;
        $zoneId = $req->zone_id;
        if(!$priceListId) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please choose a price list'
        ]));
        $merchantPriceList = MerchantPriceList::where('merchant_id',$id)->first();
        $insertOrUpdate = [
            'price_list_id' => $priceListId,
            'update_uid' => $user->id,
            'branch_id' => $user->id,
            'company_id' => $user->id
        ];

        if($zoneId) {
            $zone = Zone::where('is_deleted',0)->find($zoneId);
            if(!$zone) return ApiResponse::NotFound(__('messages.not_found',[
                'info' => 'Zone'
            ]));
            $insertOrUpdate['zone_code'] = $zone->zone_code;
            $insertOrUpdate['zone_id'] = $zoneId;
        }
        if($merchantPriceList){
            $merchantPriceList->update($insertOrUpdate);
        }else{
            $insertOrUpdate['merchant_id'] = $id;
            $insertOrUpdate['create_uid'] = $user->id;
            MerchantPriceList::create($insertOrUpdate);
        }
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

     public function setLockMerchant(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::setLockUser($user,$req->id,'merchant'));
    }

    public function setPassword(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::setNewPassword($req,$req->id,'merchant',$user));
    }

    public function deleteMerchant(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::deleteUser($req->id,'merchant',$user));
    }
}
