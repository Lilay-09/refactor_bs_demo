<?php

namespace App\Services;
use ApiResponse;
use App\Models\Disbursement;
use App\Models\MerchantPriceList;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserBank;
use App\Models\UserNotificationToken;
use App\Models\UserRoles;
use App\Models\Zone;
use DataResponse;
use DB;
use Exception;
use Hash;
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
    public static function getAuthUser($class='admin',$action='',$useSpecificClass=true){
        $user = JWTAuth::user();
        if($user){
            $hasUser = User::where('id',$user->id)->first();
            if($hasUser){
                if($class != $hasUser->account_type && $useSpecificClass) return DataResponse::Forbidden();
                if($user->delete_account || $user->lock) return DataResponse::Forbidden();
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
                    'user_name' => $hasUser->user_name,
                    'account_type' => $hasUser->account_type,
                    'company_id' => $hasUser->company_id,
                    'branch_id' => $hasUser->branch_id,
                    'system_admin' => $hasUser->system_admin,
                    'info'=> (object)[
                        'phone' => $hasUser->phone,
                        'address' => $hasUser->address,
                        'vehicle_type' => $hasUser->vehicle_type
                    ]
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
            'phone' => 'required|string|regex:/^0[0-9]{8,19}$/',
            'gender' => 'nullable|in:M,F,O',
            'dob' => 'nullable',
            'photo' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'role_id' => 'required|exists:roles,id'
        ];

        $baseMsgs = [
            'gender.in' => 'Gender must be one of M,F,O'
        ];

        if($userClass == 'admin'){
            $baseFields['password'] = 'required|string|max:20';
            $baseFields['confirm_password'] = 'nullable';
            return validator($req->all(),$baseFields);
        }else if($userClass == 'driver'){
            $baseFields['employment_date'] = 'nullable|string|max:100';
            $baseFields['shift_type'] = 'nullable|string|max:35';
            $baseFields['national_id'] = 'nullable|string|max:35';
            $baseFields['employee_type'] = 'nullable|string|max:35';
            $baseFields['vehicle_type'] = 'required|string|exists:vehicle_types,name';
            $baseFields['plate_number'] = 'nullable|string|max:50';
            $baseFields['warehouse_id'] = 'nullable';
            $baseFields['relative_name'] = 'nullable|string|max:50';
            $baseFields['relative_phone'] = 'nullable|string|max:50';
            $baseFields['relative_relationship'] = 'nullable|string|max:50';
            $baseFields['relative_address'] = 'nullable|string|max:500';
            $baseFields['salary'] = 'nullable|numeric';
            $baseFields['bank_info'] = 'nullable|array';
            $baseFields['user_name'] = 'required|max:100';
            return validator($req->all(),$baseFields);
        }else if($userClass == 'merchant'){
            $baseFields['bank_info'] = 'nullable|array';
            $baseFields['client_type_id'] = 'nullable|int';
            $baseFields['business_type'] = 'nullable|string|max:50';
            $baseFields['cod'] = 'nullable|in:1,0';
            $baseFields['cod_fee'] = 'nullable|numeric|max:100';
            $baseFields['zone_id'] = 'nullable';
            $baseFields['price_list_id'] = 'nullable|exists:price_list_names,id';
            $baseFields['referrer_uid'] = 'nullable|int';
            $baseFields['pin_address'] = 'nullable|string';
            $baseFields['otp'] = 'nullable|string';
            return validator($req->all(),$baseFields);
        }
    }


    public static function createOrUpdateUser(Request $req,$user_class='admin',$user,$id=null,$isRegistered=false){
        $validate = self::userValidation($req,$user_class);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first(),$validate->errors());
        $inputs = $validate->validated();
        $bankInfo = $inputs['bank_info'] ?? [];
        $photo = $inputs['photo'] ?? null;
        $inputs['account_type'] = $user_class;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $inputs['account_type'] = $user_class;
        $inputs['cod'] = $inputs['cod'] ?? 0;
        $inputs['dob'] = isset($inputs['dob']) ? date('Y-m-d',strtotime($inputs['dob'])) : null;
        $inputs['driver_warehouse_id'] = $inputs['warehouse_id'] ?? null;
        $roleIds = $inputs['roles'] ?? [];
        if($isRegistered){
            $inputs['lock'] = true;
            $inputs['register_status'] = 'in-progress';
        }
        if($user_class == 'admin') $inputs['has_account'] = 1;
        $nationalId = $inputs['national_id'] ?? null;
        $email = $inputs['email'] ?? null;
        $pin_address = $inputs['pin_address'] ?? null;
        $phone = $inputs['phone'];

        $priceListId = $inputs['price_list_id'] ?? 1; //:: Default = 1
        if($pin_address){
            $getLatLng = Helper::getLatLongFromGoogleMapsUrl($pin_address);
            $inputs['latitude'] = $getLatLng->latitude ?? 0;
            $inputs['longitude'] = $getLatLng->longitude ?? 0;
        }
        $zoneId = $inputs['zone_id'] ?? null;
        unset($inputs['bank_info'],$inputs['photo'],$inputs['role_id'],$inputs['client_type_id'],$inputs['zone_id']);
        DB::beginTransaction();
        try{
            if($id){
                $updateUser = User::where('account_type',$user_class)->where('is_deleted',0)->find($id);
                if(!$updateUser) return DataResponse::NotFound(__('messages.not_found',['info' => 'User']));
                if($updateUser->phone) unset($inputs['phone']);
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
                if(!$photo || Helper::isValidBase64Image($photo)) {
                    $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,'user_profile')->filename;
                    Helper::deleteImageFile($updateUser->photo_file_name,$user->company_id,'user_profile');
                }
                $update = $updateUser->update($inputs);
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
                $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,'user_profile')->filename;
                $create = User::create($inputs);
                if(!$create) return DataResponse::Error(__('messages.error',['info' => 'Fail to create']));
                Helper::setRefCode('user_code_control','users','code',$user->branch_id,$user->company_id,$create->id,null,self::$user_prefix[$user_class]);
                $userId = $create->id;
            }
            if(isset($bankInfo[0])){
                $saveUserBank = self::saveUserBanks($bankInfo,$userId,$user);
                if($saveUserBank->error) return $saveUserBank;
            }
            if($user_class == 'merchant' && $priceListId) self::saveMerchantPriceList($userId,$priceListId,$zoneId,$user);
            self::assignRolesUser($userId,$roleIds,$user_class);
            // DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error',['info' => 'Fail to create']));
        }
    }

    private static function assignRolesUser($userId,$roleIds,$class,$allowOne=true){
        if(empty($roleIds)){
            if($class == 'admin'){
                $roleIds[] = 1;
            }else if($class == 'merchant'){
                $roleIds[] = 3;
            }else if($class == 'driver'){
                $roleIds[] = 2;
            }
        }

        foreach($roleIds as $roleId){
            $exists = UserRoles::where('user_id',$userId)->where('role_id',$roleId)->first();
            if(!$exists){
                if($allowOne) UserRoles::where('user_id',$userId)->delete();
                UserRoles::create([
                    'user_id' => $userId,
                    'role_id' => $roleId
                ]);
            }else{
                if($allowOne) UserRoles::where('user_id',$userId)->where('role_id','!=',$roleId)->delete();
            }

        }
    }

    private static function saveMerchantPriceList($merchantId,$priceListId,$zoneId,$user): void{
        $found = MerchantPriceList::where('merchant_id',$merchantId)->first();
        $zoneCode = Zone::where('id',$zoneId)->value('zone_code');
        if(!$zoneCode) {
            $zoneInfo = Zone::selectRaw('id,zone_code,zone_name')->find(300);
            $zoneId = $zoneInfo->id;
            $zoneCode = $zoneInfo->zone_code;
        }
        if($found) {
            $found->update([
                'merchant_id' => $merchantId,
                'price_list_id' => $priceListId,
                'zone_id' => $zoneId,
                'zone_code' => $zoneCode,
                'update_uid' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);
        }
        else {
            MerchantPriceList::create([
                'merchant_id' => $merchantId,
                'price_list_id' => $priceListId,
                'zone_id' => $zoneId,
                'zone_code' => $zoneCode,
                'create_uid' => $user->id,
                'update_uid' => $user->id,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id
            ]);
        }
    }


    public static function saveUserBanks($bankInfo,$userId,$user){
        $keepIds = [];
        foreach($bankInfo as $bank){
            $id = $bank['id'] ?? null;
            $bank['update_uid'] = $user->id;
            $bank['branch_id'] = $user->branch_id;
            $bank['company_id'] = $user->company_id;
            $bank['user_id'] = $userId;
            $bank['is_primary'] = $bank['is_primary'] ?? false;
            // $skip = $bank['skip'] ?? false;
            if(!$id) unset($bank['id']);
            // if($skip) continue;
            $bankNumber = $bank['bank_number'] ?? null;
            $accountName = $bank['account_name'] ?? null;
            $bankName = $bank['bank_name'] ?? null;
            $fields = compact('bankNumber', 'accountName', 'bankName');
            $nonEmptyFields = array_filter($fields);

            if (!empty($nonEmptyFields) && count($nonEmptyFields) < count($fields)) {
                return DataResponse::ValidateFail(__('messages.info', [
                    'info' => 'All fields (bank_number, account_name, bank_name) must be provided together.'
                ]));
            }
            // if(!isset($bank['bank_name'])) return DataResponse::ValidateFail(__('messages.error',['info' =>'Please enter bank name']));
            if($bankName){
                $qUserBank = UserBank::where('user_id',$userId);
                if($id) $qUserBank->where('id','!=',$id);
                $existsBankInfo = $qUserBank->where('bank_name',$bankName)->where('bank_number',$bankNumber)
                ->where('account_name',$accountName)->first();
                if($existsBankInfo) return DataResponse::ValidateFail(__('messages.error',['info' => 'It seems like you try to add duplicated bank info']));
                // }

                if(!empty($nonEmptyFields)) $keepIds[] = $id;
                if($id){
                    $userBank = UserBank::where('user_id',$userId)->where('id',$id)->first();
                    if(!$userBank) return DataResponse::ValidateFail(__('messages.error',['info' => 'Wrong bank identity']));
                    $userBank->update($bank);
                }else{
                    $accountCount = UserBank::where('user_id',$userId)->count();
                    if($accountCount == 2) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'Only two accounts are allowed'
                    ]));
                    $bank['create_uid'] = $user->id;
                    UserBank::create($bank);
                }
            }
        }
        UserBank::where('user_id',$userId)->whereNotIn('id',$keepIds)->delete();
        return DataResponse::JsonResult(null,false,__('messages.saved'));
    }


    public static function deleteBank($id,$user){
        $userBank = UserBank::where('user_id',$user->id)->find($id);
        if(!$userBank) return DataResponse::NotFound('Bank not found');
        $userBank->delete();
        return DataResponse::JsonResult(null,'Deleted');
    }

    private static function createLoginValidation(Request $req){
        return validator($req->all(),[
            'login_name' => 'required|string|max:50',
            'photo' => 'nullable|string',
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
        $hpwd = Hash::make($pwd);
        $photoFile = null;
        $photo = $inputs['photo'] ?? null;
        if(Helper::isValidBase64Image($photo) || !$photo){
            $photoFile = Helper::base64ToImageFile($photo,$user->company_id,'user_profile')->filename;
        }
        $updateArr = [
            'has_account' => true,
            'login_name' => $loginName,
            'delete_account' => false,
            'lock' => false,
            'password' => $hpwd
        ];
        if($photoFile) $updateArr['photo_file_name'] = $photoFile;
        $user->update($updateArr);
        return DataResponse::JsonResult(null,false,__('messages.created'));
    }

    public static function verifyOTP(Request $req,$type){
        $validate = validator($req->all(),[
            'otp' => 'required|string|min:6|max:6',
        ]);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $otp = $inputs['otp'];
        $found = User::where('is_deleted',0)->where('account_type',$type)->where('otp',$otp)->first('id');
        if(!$found) return DataResponse::ValidateFail(__('messages.info',[
            'info' => 'Incorrect otp',
            'khInfo' => 'លេខផ្ទៀងផ្ទាត់មិនត្រូវ'
        ]));
        $found->update([
            'otp' => null,
        ]);
        return DataResponse::JsonResult(null,false,__('messages.info',[
            'info' => 'Success',
            'khInfo' => 'ផ្ទៀងផ្ទាត់រួច'
        ]));
    }

    public static function setLockUser($authUser,$userId,$type='admin'){
        $user = User::where('is_deleted',0)->where('company_id',$authUser->company_id)
        ->where('account_type',$type)
        ->selectRaw('id,code,user_name,email,gender,shift_type,vehicle_type,plate_number,phone,national_id,lock')
        ->find($userId);
        if(!$user) return DataResponse::NotFound(__('messages.not_found',[
            'info' => $type
        ]));
        $msg = '';
        if($user->lock){
            $user->update([
                'lock' => false,
            ]);
            $msg = $type.' has tured on active mode';

        }else{
            $user->update([
                'lock' => true,
            ]);
            $msg = $type.' has been locked';
        }
        return DataResponse::JsonResult(null,false,$msg);
    }


    public static function resetPassword(Request $req,$userId,$type){
        $validate = validator($req->all(),[
            'current_password' => 'required|string|min:6',
            'password' => 'required|string',
            'confirm_password' => 'required|string'
        ]);

        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = User::where('is_deleted',0)->find($userId);
        if($user){
            $curPwd = $inputs['current_password'];
            if (!Hash::check($curPwd, $user->password)) return DataResponse::ValidateFail('Invalid password');
            $pwd = $inputs['password'];
            $cfPwd = $inputs['confirm_password'];
            if($pwd != $cfPwd) return DataResponse::ValidateFail(__('messages.info',[
                'info' => 'Passwords not match!'
            ]));
            $hPwd = Hash::make($pwd);
            if($curPwd == $pwd) return DataResponse::Duplicated(__('messages.info',[
                'info' => 'Use different password',
                'khInfo' => ''
            ]));
            $user->update([
                'password' => $hPwd
            ]);
            return DataResponse::JsonResult(null,false,'Password reset');
        }
        return DataResponse::NotFound('User not found');
    }

    public static function deleteUserAccount($id,$type){
        $user = User::where('is_deleted',0)->where('account_type',$type)->find($id);
        if($user){
            $user->update([
                'has_account' => false,
                'lock' => true,
                'delete_account' => 1
            ]);
        }

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'Account'
        ]));
    }

    public static function logOut(Request $req,$user){
        JWTAuth::invalidate(JWTAuth::getToken());
        $token = UserNotificationToken::where('user_id', $user->id)->where('device_id',$req->device_id)->value('token');
        $topic = GeneralSettingService::getGeneralTopics($user->company_id,$user->account_type,$user->id);
        $cl = new CloudMessagingService();
        foreach($topic as $t){
            $cl->unsubscribeTopic($user,$t,$token);
        }
        return DataResponse::JsonResult(null,false,__('messages.info',[
            'info' => 'Logged Out',
        ]));
    }

    public static function setNewPassword(Request $req,$userId,$userclass,$authUser){
        $user = User::where('is_deleted',0)->where('account_type',$userclass)->find($userId);
        if(!$user) return DataResponse::NotFound('User not found');
        if(!$user->has_account) return DataResponse::ValidateFail('This user does not have account!');
        $password = $req->password;
        $cfConfirm = $req->confirm_password;
        if(!$password) return DataResponse::ValidateFail('Password is required');
        if(strlen($password) < 6) return DataResponse::ValidateFail('Password must be at least 6 characters');
        if($cfConfirm != $password) return DataResponse::ValidateFail('Confirm password is incorrect');
        $user->update([
            'update_uid' => $authUser->id,
            'password' => Hash::make($password)
        ]);
        return DataResponse::JsonResult(null,false,'New password has been set.');
    }

    public static function deleteUser($id,$type,$authUser){
        $user = User::where('is_deleted',0)->where('account_type',$type)->find($id);
        if($user){
            if($type != 'admin'){
                $disKey = $type.'_disbursement_id';
                $package = Package::where('is_deleted',0)->where('outstanding',0)->first();
                if(!$package->{$disKey}) return DataResponse::ValidateFail($type.' still has payment that is not paid.');
                $payment = Payment::where('is_deleted',0)->where('approved',0)->first();
                $disbursement = Disbursement::where('is_deleted',0)->where('approved',0)->where('type','payment')->first();
                if($payment || $disbursement) return DataResponse::ValidateFail('Found some payments that are not paid yet!');
            }
            $user->update([
                'is_deleted' => false,
                'delete_datetime' => now(),
                'delete_uid' => $authUser->id
            ]);
        }

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'User'
        ]));
    }

    private function checkPayment($rows,$key){

    }

}
