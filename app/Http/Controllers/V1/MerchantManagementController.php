<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MerchantPriceList;
use App\Models\PriceList;
use App\Models\User;
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
        $search = $req->search;
        $priceList = DB::table('price_list_names as n')
        ->selectRaw('n.id,n.name,mpl.merchant_id')->join('merchant_price_list as mpl','mpl.price_list_id','n.id')
        ->get();
        $query = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type',$this->userClass)
        ->with(['merchantType:id,name','bank_accounts:id,bank_name,bank_number,account_name,user_id,is_primary']);
        // ->selectRaw('id,cod_fee,code,name_km,user_name,email,gender,business_type,phone,client_type_id,address,cod,pin_address,photo_file_name,lock,has_account,photo_file_name');
        if($statusId !== null && $statusId>=0) {
            $query->where('lock',$statusId ? 0 : 1);
        }

        if($search){
            $query->where(function($q) use ($search){
                $q->where('code','ilike','%'.$search.'%')
                ->orWhere('user_name','ilike','%'.$search.'%')
                ->orWhere('name_km','ilike','%'.$search.'%')
                ->orWhere('phone','ilike','%'.$search.'%');
            });
        }
        $merhcants = $query->orderByDesc('id')->get();
        foreach($merhcants as $m){
            $merchantPriceList = $this->getMerchantPriceList($priceList,$m->id);
            $m->referrer = null;
            $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
            $m->price_list_name = $merchantPriceList?->name;
            $m->client_type = $m->merchantType?->name;
            $m->create_by = ($m->create_uid == $m->id) ? 'Self': 'Admin';
            foreach($m->bank_accounts as $b){
                if($b->is_primary) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
                if(!$b->bank_account) $m->bank_account = GeneralSettingService::concatBankInfo($b->bank_name,$b->bank_number,$b->account_name);
            }
            $m->image_url = Helper::getImageUrl($m->photo_file_name,$user->company_id,'user_profile');
            unset($m->merchantType,$m->bank_accounts,$m->photo_file_name);
        }
        return ApiResponse::Pagination($merhcants,$req);
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
        ->with(['bank_accounts:id,bank_name,bank_number,account_name,user_id,is_primary'])
        // ->selectRaw('id,cod_fee,code,name_km,user_name,email,gender,photo_file_name,business_type,phone,client_type_id,address,referrer_uid,cod')
        ->find($id);
        $priceList = DB::table('price_list as pl')->join('price_list_names as n','n.id','pl.price_list_name_id')
        ->selectRaw('pl.id,n.name,mpl.merchant_id')->join('merchant_price_list as mpl','mpl.price_list_id','pl.id')
        ->where('mpl.merchant_id',$id)->first();
        if(!$merchant) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Merchant']));
        $merchant->image_url = Helper::getImageUrl($merchant->photo_file_name,$user->company_id,'user_profile');
        if($priceList){
            $merchant->price_list_id = $priceList->id;
            $cod = $merchant->cod;
            $merchant->cod = $cod ? 1:0;
        }
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
        if(!$priceListId) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Please choose a price list'
        ]));
        $merchantPriceList = MerchantPriceList::where('merchant_id',$req->id)->first();
        if($merchantPriceList){
            $merchantPriceList->update([
                'price_list_id' => $priceListId,
                'update_uid' => $user->id,
                'branch_id' => $user->id,
                'company_id' => $user->id
            ]);
        }else{
            MerchantPriceList::create([
                'merchant_id' => $id,
                'price_list_id' => $priceListId,
                'create_uid' => $user->id,
                'update_uid' => $user->id,
                'branch_id' => $user->id,
                'company_id' => $user->id
            ]);
        }
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

     public function setLockMerchant(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::flex(UserService::setLockUser($user,$req->id,'merchant'));
    }

}
