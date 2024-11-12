<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\User;
use App\Services\UserService;
use DB;
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
        $priceList = DB::table('price_list as pl')->join('price_list_names as n','n.id','pl.price_list_name_id')
        ->selectRaw('pl.id,n.name,mpl.merchant_id')->join('merchant_price_list as mpl','mpl.price_list_id','pl.id')
        ->get();
        $query = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type',$this->userClass)
        ->with(['merchantType:id,name','bank_accounts:id,bank_name,bank_number,account_name,user_id,is_primary'])
        ->selectRaw('id,cod_fee,code,name_km,user_name,email,gender,business_type,phone,client_type_id,address,cod,pin_address');
        $merhcants = $query->orderByDesc('id')->get();
        foreach($merhcants as $m){
            $merchantPriceList = $this->getMerchantPriceList($priceList,$m->id);
            $m->referrer = null;
            $m->price_list_name = $merchantPriceList?->name;
            $m->client_type = $m->merchantType?->name;
            $m->create_by = ($m->create_uid == $m->id) ? 'Self': 'Admin';
            foreach($m->bank_accounts as $b){
                if($b->is_primary) $m->bank_account = $b->bank_name.'|'.$b->bank_number.'|'.$b->account_name;
                if(!$b->bank_account) $m->bank_account = $b->bank_name.'|'.$b->bank_number.'|'.$b->account_name;
            }
            unset($m->merchantType,$m->bank_accounts);
        }
        return ApiResponse::Pagination($merhcants,$req);
    }

    public function getOneMerchant(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $merchant = User::where('is_deleted',0)->where('company_id',$user->company_id)
        ->where('account_type',$this->userClass)
        ->with(['bank_accounts:id,bank_name,bank_number,account_name,user_id,is_primary'])
        ->selectRaw('id,cod_fee,code,name_km,user_name,email,gender,business_type,phone,client_type_id,address,referrer_uid,cod')
        ->find($id);
        $priceList = DB::table('price_list as pl')->join('price_list_names as n','n.id','pl.price_list_name_id')
        ->selectRaw('pl.id,n.name,mpl.merchant_id')->join('merchant_price_list as mpl','mpl.price_list_id','pl.id')
        ->where('mpl.merchant_id',$id)->first();
        if(!$merchant) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Merchant']));
        if($priceList){
            $merchant->price_list_id = $priceList->id;
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
        $createDriver = UserService::createOrUpdateUser($req,$this->userClass,$user,$id);
        return ApiResponse::flex($createDriver);
    }


    public function createMerchantAccount(){

    }

}
