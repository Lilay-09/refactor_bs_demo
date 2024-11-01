<?php

namespace App\Services;
use ApiResponse;
use App\Models\MerchantPriceList;
use App\Models\User;
use App\Models\UserBank;
use App\Models\UserRoles;
use DataResponse;
use DB;
use Exception;
use Helper;
use Log;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserService
{
    // Your service methods go here

    protected static $user_prefix = [
        'admin' => 'JSA',
        'driver' => 'JSD',
        'merchant' => 'JSM'
    ];
    public static function getAuthUser($class='admin',$action=''){
        $user = JWTAuth::user();
        if($user){
            $hasUser = User::where('id',$user->id)->first();
            if($hasUser){
                if($class != $hasUser->account_type) return DataResponse::Forbidden();
                $validActions = ['create','update','modify','void','delete'];
                $roles = UserRoles::where('user_id',$hasUser->id)->with(['role:id,name'])->selectRaw('role_id')->get();
                $hasUser->roles = $roles;
                $action = $action ?? 'void';
                if($action && !in_array($action,$validActions)){
                    return DataResponse::ValidateFail('You action must be one of '.implode(',',$validActions));
                }
                // if(!$hasUser->system_admin && $action == 'void'){
                //     return DataResponse::Forbidden();
                // }
                foreach($roles as $role){
                    $role->id = $role->role->id;
                    $role->name = $role->role->name;
                    unset($role->role);
                }
                return DataResponse::JsonRaw([
                    'error'=>false,
                    'status_code' => 200,
                    'status' => 'OK',
                    'id' => $hasUser->id,
                    'account_type' => $hasUser->account_type,
                    'company_id' => $hasUser->company_id,
                    'branch_id' => $hasUser->branch_id,
                    'system_admin' => $hasUser->system_admin,
                    'user'=>$hasUser
                ]);
            }
        }
        return DataResponse::Unauthorized();
    }

    public function userPermissions(){}

    public static function getRolesByUsers($userId){
        return UserRoles::from('user_roles as ur')->where('ur.user_id',$userId)->join('roles as r','r.id','=','ur.role_id')->selectRaw('r.name as role,ur.role_id,r.description')->get();
    }


    private static function userValidation(Request $req,$userClass){
        $baseFields = [
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'user_name' => 'nullable|max:100',
            'name_km' => 'nullable|max:100',
            'email' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'gender' => 'required|in:M,F,O',
            'photo' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:6|max:20'
        ];
        $baseMsgs = [
            'gender.in' => 'Gender must be one of M,F,O'
        ];
        if($userClass == 'admin'){
            return validator($req->all(),$baseFields);
        }else if($userClass == 'driver'){
            $baseFields['employment_date'] = 'nullable|string|max:100';
            $baseFields['shift_type'] = 'nullable|string|max:35';
            $baseFields['vehicle_type'] = 'required|string|exists:vehicle_types,name';
            $baseFields['plate_number'] = 'nullable|string|max:50';
            $baseFields['relative_name'] = 'nullable|string|max:50';
            $baseFields['relative_phone'] = 'nullable|string|max:50';
            $baseFields['relative_relationship'] = 'nullable|string|max:50';
            $baseFields['relative_address'] = 'nullable|string|max:500';
            $baseFields['salary'] = 'nullable|numeric';
            $baseFields['bank_info'] = 'nullable|array';
            $baseFields['user_name'] = 'required|max:100';
            return validator($req->all(),$baseFields);
        }else if($userClass == 'merchant'){
            $baseFields['client_type_id'] = 'nullable|int';
            $baseFields['business_type'] = 'nullable|string|max:50';
            $baseFields['cod'] = 'nullable|in:1,0';
            $baseFields['cod_fee'] = 'nullable|numeric|max:100';
            $baseFields['price_list_id'] = 'required|exists:price_list,id';
            $baseFields['referrer_uid'] = 'nullable|int';
            $baseFields['pin_address'] = 'nullable|string';
            return validator($req->all(),$baseFields);
        }
    }


    public static function createOrUpdateUser(Request $req,$user_class='admin',$user,$id=null){
        $validate = self::userValidation($req,$user_class);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first(),$validate->errors());
        $inputs = $validate->validated();
        $bankInfo = $inputs['bank_info'] ?? [];
        $photo = $inputs['photo'];
        $inputs['account_type'] = $user_class;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['account_type'] = $user_class;
        if($user_class == 'admin') $inputs['has_account'] = 1;
        $nationalId = $inputs['national_id'] ?? null;
        $email = $inputs['email'] ?? null;
        $pin_address = $inputs['pin_address'] ?? null;
        $phone = $inputs['phone'];
        $priceListId = $inputs['price_list_id'] ?? null;
        if($pin_address){
            $getLatLng = Helper::getLatLongFromGoogleMapsUrl($pin_address);
            $inputs['latitude'] = $getLatLng->latitude ?? 0;
            $inputs['longitude'] = $getLatLng->longitude ?? 0;
        }
        unset($inputs['bank_info'],$inputs['photo'],$inputs['role_id']);
        DB::beginTransaction();
        try{
            if($id){
                $user = User::where('account_type',$user_class)->where('is_deleted',0)->find($id);
                if(!$user) return DataResponse::NotFound(__('messages.not_found',['info' => 'User']));
                $existsEmail = User::where('account_type',$user_class)->where('is_deleted',0)->where('id','!=',$id)->whereNotNull('email')->where('email',$email)->first();
                $existsPhone = User::where('account_type',$user_class)->where('is_deleted',0)->where('id','!=',$id)->where('phone',$phone)->first();
                $existsNationalId = User::where('account_type',$user_class)->where('is_deleted',0)->where('id','!=',$id)->whereNotNull('national_id')->where('national_id',$nationalId)->first();
                if($existsEmail) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'Email has already taken.'
                ]));
                if($existsNationalId) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'National ID is already exists.'
                ]));
                if($existsPhone) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'Phone number('.$phone.') has already taken.'
                ]));
                $update = $user->update($inputs);
                if(!$update) return DataResponse::Error(__('messages.error',['info' => 'Fail to update']));
                $userId = $id;
            }else{
                $inputs['create_uid'] = $user->id;
                $existsEmail = User::where('account_type',$user_class)->where('is_deleted',0)->whereNotNull('email')->where('email',$email)->first();
                $existsPhone = User::where('account_type',$user_class)->where('is_deleted',0)->where('phone',$phone)->first();
                $existsNationalId = User::where('account_type',$user_class)->where('is_deleted',0)->whereNotNull('national_id')->where('national_id',$nationalId)->first();
                if($existsEmail) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'Email has already taken.'
                ]));
                if($existsNationalId) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'National ID is already exists.'
                ]));
                if($existsPhone) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'Phone number('.$phone.') has already taken.'
                ]));

                $create = User::create($inputs);
                if(!$create) return DataResponse::Error(__('messages.error',['info' => 'Fail to create']));
                Helper::setRefCode('user_code_control','users','code',$user->branch_id,$user->company_id,$create->id,null,self::$user_prefix[$user_class]);
                $userId = $create->id;
            }
            if(isset($bankInfo[0])){
                $saveUserBank = self::saveUserBanks($bankInfo,$userId,$user);
                if($saveUserBank->error) return $saveUserBank;
            }
            if($user_class == 'merchant') self::saveMerchantPriceList($userId,$priceListId,$user);
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error',['info' => 'Fail to create']));
        }
    }

    private static function saveMerchantPriceList($merchantId,$priceListId,$user): void{
        $found = MerchantPriceList::where('merchant_id',$merchantId)->first();
        if($found) {
            $found->update([
            'merchant_id' => $merchantId,
            'price_list_id' => $priceListId,
            'update_uid' => $user->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ]);
        }
        else {
            MerchantPriceList::create([
            'merchant_id' => $merchantId,
            'price_list_id' => $priceListId,
            'create_uid' => $user->id,
            'update_uid' => $user->id,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id
        ]);
        }
    }

    private static function saveUserBanks($bankInfo,$userId,$user){
        foreach($bankInfo as $bank){
            $id = $bank['id'] ?? null;
            $bank['update_uid'] = $user->id;
            $bank['branch_id'] = $user->branch_id;
            $bank['company_id'] = $user->company_id;
            $bank['user_id'] = $userId;
            $bankNumber = $bank['bank_number'] ?? null;
            $accountName = $bank['account_name'] ?? null;
            if(!isset($bank['bank_name'])) return DataResponse::ValidateFail(__('messages.error',['info' =>'Please enter bank name']));
            $qUserBank = UserBank::where('user_id',$userId);
            if($id) $qUserBank->where('id','!=',$id);
            // if($existsBank){
            $existsBankInfo = $qUserBank->where('bank_name',$bank['bank_name'])->where('bank_number',$bankNumber)
            ->where('account_name',$accountName)->first();
            if($existsBankInfo) return DataResponse::ValidateFail(__('messages.error',['info' => 'It seems like you try to add duplicated bank info']));
            // }
            if($id){
                $userBank = UserBank::where('user_id',$userId)->where('id',$id)->first();
                if(!$userBank) return DataResponse::ValidateFail(__('messages.error',['info' => 'Wrong bank identity']));
                $userBank->update($bank);
            }else{
                $bank['create_uid'] = $user->id;
                UserBank::create($bank);
            }
        }
        return DataResponse::JsonResult(null,false,__('messages.saved'));
    }


    private static function createLoginValidation(Request $req){
        return validator($req->all(),[
            'login_name' => 'required|string|max:50',
            'password' => 'required|string|max:50',
            'confirm_password' => 'required|string|max:50',
        ]);
    }

    public static function createLoginAccount(Request $req,$userId,$userClass,$authUser){
        $user = User::where('company_id',$authUser->company_id)->where('is_deleted',0)->where('account_type',$userClass)->find($userId);
        if(!$user) return DataResponse::NotFound(__('messages.not_found',['info' => 'User']));
        if($user->has_account) return DataResponse::Duplicated('User ('.$user->user_name.') already has an account!');
        $validate = self::createLoginValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $loginName = $inputs['login_name'];
        $pwd = $inputs['password'];
        $cfPwd = $inputs['confirm_password'];
        $existLoginName = User::where('company_id',$authUser->company_id)->where('login_name',$loginName)->where('is_deleted',0)->where('account_type',$userClass)->first();
        if($existLoginName) return DataResponse::Duplicated('Please use another login name!, this one is already taken.');
        if($pwd !== $cfPwd) return DataResponse::ValidateFail(__('messages.error',['info' => 'Password not match !']));
        $hpwd = \Hash::make($pwd);
        $user->update([
            'has_account' => true,
            'login_name' => $loginName,
            'password' => $hpwd
        ]);
        return DataResponse::JsonResult(null,false,__('messages.created'));
    }
}
