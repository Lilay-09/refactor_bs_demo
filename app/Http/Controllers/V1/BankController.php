<?php

namespace App\Http\Controllers\V1;

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
            'name' => 'required|string|max:30',
            'photo' => 'nullable|string',
        ]);
    }

    public function getBanks(Request $req){
        $user = UserService::getAuthUser();
        $query = Bank::where('company_id',$user->company_id)->where('is_deleted',0)->orderByDesc('id')
        ->selectRaw('id,name');
        $banks = $query->get();
        foreach ($banks as $bank){
            $bank->image_url = Helper::getImageUrl($bank->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::Pagination($banks);
    }

    public function getOneBank(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $bank = Bank::where('company_id',$user->company_id)->where('is_deleted',0)->selectRaw('id,name')->find($id);
        if($bank){
            $bank->image_url = Helper::getImageUrl($bank->photo_file_name,$user->company_id,$this->imgDir);
        }
        return ApiResponse::JsonResult($bank);
    }

    public function createBank(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->bankValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['company_id'] = $user->company_id;
        $inputs['branch_id'] = $user->branch_id;
        $photo = $inputs['photo'] ?? null;
        unset($inputs['photo']);
        $existsBank = Bank::where('is_deleted',0)->where('name',$inputs['name'])->first();
        if($existsBank) return ApiResponse::JsonResult(null,__('messages.error', ['info' => 'Bank already exists']));
        if($photo){
            $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
        }
        $create = Bank::create($inputs);
        if(!$create) {
            if(!$inputs['photo_file_name']) Helper::deleteImageFile($inputs['photo_file_name'],$user->company_id,$this->imgDir);
            return ApiResponse::Error('Fail to create');
        }
        return ApiResponse::JsonResult(null,__('messages.created'));
    }


    public function updateBank(Request $req){
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
        $photo = $inputs['photo'] ?? null;
        $existsBank = Bank::where('is_deleted',0)->where('name',$inputs['name'])->where('id','!=',$id)->first();
        if($existsBank) return ApiResponse::JsonResult(null,__('messages.error', ['info' => 'Bank already exists']));
        if(Helper::isValidBase64Image($photo) || !$photo){
            Helper::deleteImageFile($bank->photo_file_name,$user->company_id,$this->imgDir);
            $inputs['photo_file_name'] = null;
            if(Helper::isValidBase64Image($photo))
                $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,$this->imgDir);
        }
        $update = $bank->update($inputs);
        if(!$update) return ApiResponse::Error('Fail to update');
        return ApiResponse::JsonResult(null,__('messages.updated'));
    }

    public function deleteBank(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $bank = Bank::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$bank) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Bank']));
        $bank->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,false,'Deleted');
    }
}
