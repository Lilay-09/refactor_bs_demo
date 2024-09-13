<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class BankController extends Controller
{
    //
    protected $imgDir = 'payment_method';
    function bankValidation(Request $req){
        return validator($req->all(),[
            'inactive' => 'required|in:0,1',
            'name' => 'required|string|max:30',
            'name_kh' => 'nullable|string|max:30',
            'kh_qr' => 'nullable|string',
            'qr' => 'nullable|string'
        ]);
    }

    public function getBanks(Request $req){
        $user = UserService::getAuthUser();
        $query = Bank::where('company_id',$user->company_id)->where('void',0)->orderByDesc('created_at');
        $banks = $query->get();
        foreach ($banks as $bank){
            $bank->qr = Helper::getImageUrl($bank->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::Pagination($banks);
    }

    public function getBank(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $bank = Bank::where('company_id',$user->company_id)->where('void',0)->find($id);
        if($bank){
            $bank->qr = Helper::getImageUrl($bank->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::JsonResult($bank);
    }

    public function create(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->bankValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $qr = $inputs['qr'] ?? null;
        if($qr){
            $inputs['photo_file_name'] = Helper::base64ToImageFile($qr,$user->company_id,$this->imgDir);
        }
        $create = Bank::create($inputs);
        if(!$create) {
            if(!$inputs['photo_file_name']) Helper::deleteImageFile($inputs['photo_file_name'],$user->company_id,$this->imgDir);
            return ApiResponse::Error('Fail to create');
        }
        return ApiResponse::JsonResult(null,false,'Created');
    }


    public function update(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->bankValidation($req);
        $id = $req->id;
        $bank = Bank::where('company_id',$user->company_id)->find($id);
        if(!$bank) return ApiResponse::NotFound('Bank not found');
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $qr = $inputs['qr'] ?? null;
        if(Helper::isValidBase64Image($qr) || !$qr){
            Helper::deleteImageFile($bank->photo_file_name,$user->company_id,$this->imgDir);
            $inputs['photo_file_name'] = null;
        }
        if($qr){
            $inputs['photo_file_name'] = Helper::base64ToImageFile($qr,$user->company_id,$this->imgDir);
        }
        $update = $bank->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to update');
        return ApiResponse::JsonResult(null,false,'Updated');
    }

    public function void(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $bank = Bank::where('company_id',$user->company_id)->where('void',0)->find($id);
        if(!$bank) return ApiResponse::NotFound('Bank not found');
        $bank->update([
            'void' => 1,
            'void_uid' => $user->id
        ]);
        return ApiResponse::JsonResult(null,false,'Voided');
    }
}
