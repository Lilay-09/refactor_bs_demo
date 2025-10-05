<?php

namespace App\Services;
use ApiResponse;
use App\Models\Disbursement;
use App\Models\DriverCommission;
use App\Models\MerchantPriceList;
use App\Models\Package;
use App\Models\Payment;
use App\Models\TelegramBot;
use App\Models\TelegramBotUser;
use App\Models\User;
use App\Models\UserBank;
use App\Models\UserNotificationToken;
use App\Models\UserRoles;
use App\Models\Zone;
use DataResponse;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Hash;
use Helper;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDO;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserService
{
    // Your service methods go here

    protected static $user_prefix = [
        'admin' => 'NGA',
        'driver' => 'NGD',
        'merchant' => 'NGM'
    ];

    public static function getUserAuthAccess($class='admin',$action='',$useSpecificClass=true){
        $user = JWTAuth::user();
        if($user){
            $hasUser = User::where('id',$user->id)->where('is_deleted',0)->selectRaw('id,username,phone,account_type,company_id,lock,branch_id,system_admin,vehicle_type,address,delete_account')->first();
            if($hasUser){
                if(!$user->system_admin){
                    if($class != $hasUser->account_type && $useSpecificClass) return DataResponse::Forbidden();
                    if($hasUser->delete_account || $hasUser->lock) return DataResponse::Unauthorized();
                }
                $validActions = ['create','update','modify','void','delete'];
                // $roles = UserRoles::where('user_id',$hasUser->id)->with(['role:id,name'])->selectRaw('role_id')->get();
                // $hasUser->roles = $roles;
                // $action = $action ?? 'void';
                if($action && !in_array($action,$validActions)){
                    return DataResponse::ValidateFail('You action must be one of '.implode(',',$validActions));
                }
                // if(!$hasUser->system_admin && $action == 'void'){
                //     return DataResponse::Forbidden();
                // }
                // foreach($roles as $role){
                //     $role->id = $role->role->id;
                //     $role->name = $role->role->name;
                //     unset($role->role);
                // }
                return DataResponse::JsonRaw([
                    'error'=>false,
                    'status_code' => 200,
                    'status' => 'OK',
                    'id' => $hasUser->id,
                    'username' => $hasUser->username,
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
    /**
     * Get the authenticated user
     * @param string $class
     * @param string $action
     * @param bool $useSpecificClass
     * @return object
     */
    public static function getAuthUser(?string $class='admin',?string $action='',?BOOL $useSpecificClass=true): object{
        $user = JWTAuth::user();
        if($user){
            $info = (object)[
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'warehouse_id' => $user->driver_warehouse_id ?? null,
                    'pin_address' => $user?->pin_address,
                    'vehicle_type' => $user?->vehicle_type,
                    'latitude' => $user?->latitude,
                    'longitude' => $user?->longitude
            ];
            if($class == 'driver'){
                $info->isSalaryDay = $user->salary_date
                ? (now()->day === \Carbon\Carbon::parse($user->salary_date)->day)
                : false;
            }
            return DataResponse::JsonRaw([
                'error'=>false,
                'status_code' => 200,
                'status' => 'OK',
                'id' => $user->id,
                // 'username' => $user->username,
                'username' => $user->username,
                'account_type' => $user->account_type,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'system_admin' => $user->system_admin,
                'info'=> $info
            ]);
        }
        return DataResponse::Unauthorized();
    }

    public static function getRolesByUsers($userId,$lang='en'){
        return UserRoles::from('user_roles as ur')->where('ur.user_id',$userId)->join('roles as r','r.id','=','ur.role_id')->selectRaw('r.name_'.$lang.' as role,ur.role_id,r.description')->get();
    }

    private static function userValidation(Request $req,$userClass){
        $baseFields = [
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'username' => 'nullable|max:100',
            'name_km' => 'nullable|max:100',
            'email' => 'nullable|string|max:100',
            'phone' => 'required|string|regex:/^0[0-9]{8,19}$/',
            'gender' => 'nullable|in:M,F,O',
            'dob' => 'nullable',
            'photo' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'register_channel' => 'nullable|string',
            'login_name' => 'nullable|string|max:20',
            'branch_id' => 'nullable|int'
        ];

        $baseMsgs = [
            'gender.in' => 'Gender must be one of M,F,O'
        ];

        if($userClass == 'admin'){
            $baseFields['password'] = 'nullable|string|max:20';
            $baseFields['confirm_password'] = 'nullable';
            $baseFields['login_name'] = 'nullable';
            $baseFields['role_id'] = 'required|exists:roles,id';
            $baseFields['branch_id'] = 'required|int';
            return validator($req->all(),$baseFields);
        }else if($userClass == 'driver'){
            $baseFields['employment_date'] = 'nullable|string|max:100';
            $baseFields['shift_type'] = 'nullable|string|max:35';
            $baseFields['national_id'] = 'nullable|string|max:35';
            $baseFields['employee_type'] = 'nullable|string|max:35';
            $baseFields['vehicle_type'] = 'required|string|exists:vehicle_types,name_en';
            $baseFields['plate_number'] = 'nullable|string|max:50';
            $baseFields['warehouse_id'] = 'nullable';
            $baseFields['relative_name'] = 'nullable|string|max:50';
            $baseFields['relative_phone'] = 'nullable|string|max:50';
            $baseFields['relative_relationship'] = 'nullable|string|max:50';
            $baseFields['relative_address'] = 'nullable|string|max:500';
            $baseFields['salary'] = 'nullable|numeric';
            $baseFields['bank_info'] = 'nullable|array';
            $baseFields['username'] = 'required|max:100';
            $baseFields['salary_date'] = 'nullable';
            if(!$req->id){
                $baseFields['has_commission'] = 'required|boolean';
            }
            return validator($req->all(),$baseFields);
        }else if($userClass == 'merchant'){
            $baseFields['bank_info'] = 'nullable|array';
            $baseFields['client_type_id'] = 'nullable';
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
        $branchId = $inputs['branch_id'] ?? $user->branch_id;
        $companyId = $user->company_id ?? 1;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] =$branchId;
        $inputs['company_id'] = $companyId;
        $inputs['account_type'] = $user_class;
        $inputs['cod'] = $inputs['cod'] ?? 0;
        $inputs['dob'] = isset($inputs['dob']) ? date('Y-m-d',strtotime($inputs['dob'])) : null;
        $inputs['driver_warehouse_id'] = $inputs['warehouse_id'] ?? null;
        $roleId = $inputs['role_id'] ?? null;
        $roleIds = $inputs['roles'] ?? ($roleId ? [$roleId] : []);

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
            if(Helper::isShortGoogleMapUrl($pin_address)){
                $geoRes = new GeoResolverService();
                $xM = $geoRes->fromShortUrl($pin_address);
                $inputs['latitude'] = $xM['lat'] ?? 0;
                $inputs['longitude'] = $xM['lng'] ?? 0;
            }else{
                $getLatLng = Helper::getLatLongFromGoogleMapsUrl($pin_address);
                $inputs['latitude'] = $getLatLng->latitude ?? 0;
                $inputs['longitude'] = $getLatLng->longitude ?? 0;
            }

        }

        $empDate = $inputs['employment_date'] ?? null;
        $inputs['employment_date'] = $empDate ? Helper::dateYMD($empDate):null;

        $zoneId = $inputs['zone_id'] ?? null;
        $pwd = $inputs['password'] ?? null;
        if($pwd) $inputs['password'] = Hash::make($pwd);
        unset($inputs['bank_info'],$inputs['photo'],$inputs['role_id'],$inputs['zone_id']);
        $prefix = self::$user_prefix[$user_class];
        // if($user_class == 'driver'){
        //     if(isset($inputs['has_commission'])){
        //         $prefix .= 'PB'.$branchId;
        //     }else {
        //         $prefix .= 'FB'.$branchId;
        //     }
        // }else if($user_class == 'merchant'){
        //     $prefix .= 'B'.$branchId;
        // }
        // else{
        //     $prefix .= 'B'.$branchId;
        // }
        DB::beginTransaction();
        try{
            if($id){
                 $existLoginName = User::where('company_id',$user->company_id)
                    ->where('id','!=',$id)
                    ->where('login_name',$inputs['login_name'])
                    ->where('is_deleted',0)
                    ->orderByDesc('id')
                    ->where('account_type',$user_class)->first();
                if($existLoginName) return DataResponse::Duplicated('Please use another login name!, this one is already taken.');
                unset($inputs['password'],$inputs['register_status']);
                $updateUser = User::where('account_type',$user_class)->where('is_deleted',0)->find($id);
                if(!$updateUser) return DataResponse::NotFound(__('messages.not_found',['info' => 'User']));
                // if($updateUser->phone) unset($inputs['phone']);
                $existsEmail = User::where('account_type',$user_class)->where('is_deleted',0)->where('id','!=',$id)->whereNotNull('email')->where('email',$email)->first();
                $existsPhone = User::where('account_type',$user_class)->where('is_deleted',0)->where('id','!=',$id)->where('phone',$phone)->first();
                $existsNationalId = User::where('account_type',$user_class)->where('is_deleted',0)->where('id','!=',$id)->whereNotNull('national_id')->where('national_id',$nationalId)->first();
                if($existsEmail) {
                    return DataResponse::Duplicated(__('messages.error',[
                        'info' => 'Email has already taken.'
                    ]));
                }
                if($existsNationalId) {
                    return DataResponse::Duplicated(__('messages.error',[
                        'info' => 'National ID is already exists.'
                    ]));
                }
                if($existsPhone) {
                    return DataResponse::Duplicated(__('messages.error',[
                        'info' => 'Phone number('.$phone.') has already taken.'
                    ]));
                }
                if(!$photo || Helper::isValidBase64Image($photo)) {
                    $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,'user_profile')->filename;
                    Helper::deleteImageFile($updateUser->photo_file_name,$user->company_id,'user_profile');
                }

                $update = $updateUser->update($inputs);
                if(!$update) return DataResponse::Error(__('messages.error',['info' => 'Fail to update']));
                $userId = $id;
            }else{
                $inputs['registered_datetime'] = now();
                if(!isset($inputs['password']) && $user_class == 'admin') return DataResponse::ValidateFail(__('messages.info',[
                    'info' => 'Password must be provided',
                    'khInfo' => 'សូមបញ្ចូលលេខសំងាត់',
                ]));
                $inputs['create_uid'] = $user->id;
                $existsEmail = User::where('account_type',$user_class)->where('is_deleted',0)->whereNotNull('email')->where('email',$email)->first();
                $existsPhone = User::where('account_type',$user_class)->where('is_deleted',0)->where('phone',$phone)->first();
                $existsNationalId = User::where('account_type',$user_class)->where('is_deleted',0)->whereNotNull('national_id')->where('national_id',$nationalId)->first();
                if($existsEmail) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'Email has already taken.',
                    'khInfo' => 'អ៊ីម៉ែលមានរូចហើយ'
                ]));
                if($existsNationalId) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'National ID is already exists.',
                    'khInfo' => 'អត្តសញ្ញាណជាតិជាន់គ្នា'
                ]));
                if($existsPhone) return DataResponse::Duplicated(__('messages.error',[
                    'info' => 'Phone number('.$phone.') has already taken.',
                    'khInfo' => 'លេខ('.$phone.') មានរូចហើយ.'
                ]));
                $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,'user_profile')->filename;
                $create = User::create($inputs);
                if(!$create) return DataResponse::Error(__('messages.error',['info' => 'Fail to create']));

                self::setRefCode('user_code_control','users','code',$user->branch_id,$user->company_id,$create->id,$prefix);
                $userId = $create->id;
                // return $userId;
            }

            if(isset($bankInfo[0])){
                $saveUserBank = self::saveUserBanks($bankInfo,$userId,$user);
                if($saveUserBank->error) return $saveUserBank;
            }
            if($user_class == 'merchant') {
                $userShopService = new UserShopService();
                if(!empty($req->shops)){
                    foreach ($req->shops as $shop) {
                        // Clone the original request to avoid mutation
                        $shopReq = new Request([
                            'owner_id'        => $userId,
                            'name_en'         => $shop['shop_name_en'] ?? null,
                            'name_km'         => $shop['shop_name_km'] ?? null,
                            'shop_type'       => $shop['shop_type'] ?? $req->input('business_type'),
                            'product_type_id' => $shop['product_type_id'] ?? null,
                            'phone' => $shop['phone'] ?? null,
                            'est_pcs' => $shop['est_pcs'] ?? 0,
                            "city" =>  $shop['city'] ?? null,
                            "district" => $shop['district'] ?? null,
                            "commune" => $shop['commune'] ?? null,
                            'address' => $shop['address'] ?? null
                        ]);

                        // Save the shop using your service
                        $saveShop = $userShopService->saveShop($shopReq, $user);
                        if($saveShop->error) return $saveShop;
                    }
                }

                // foreach($req->shops as $shop){
                //     $shopReq = clone $req;
                //     $shopReq->merge([
                //         'owner_id' => $userId,
                //         'name_en' => $shop['shop_name_en'],
                //         'name_km' => $shop['shop_name_km'],
                //         'shop_type' => $shop['shop_type'] ?? $inputs['business_type']
                //     ]);
                //     $userShopService->saveShop($shopReq,$user);
                // }
                if($priceListId) {
                    self::saveMerchantPriceList($userId,$priceListId,$zoneId,$user);
                }
            }
            self::assignRolesUser($userId,$roleIds,$user_class);
            DB::commit();
            return DataResponse::JsonResult(null,false,__('messages.saved'));
        }catch(Exception $e){
            DB::rollBack();
            Log::error($e->getMessage());
            return DataResponse::Error(__('messages.error',['info' => 'Fail to create']));
        }
    }

    static function saveEmploymentHistory(){

    }

    // static function changeEmployeePolicy




    static function setRefCode($tbl_code_control,$target_tbl,$target_col,$branch_id,$company_id,$newID,$prefix,$len = 5,$issue_date = null){
        if (!$len) $len = 5;
        if (!$newID) return DataResponse::ValidateFail('Identity should be input');
        $year = $issue_date?date('Y', strtotime($issue_date)):date('Y');
        $qRow = DB::table($tbl_code_control . " as c")->where('c.branch_id', $branch_id)
        ->where('prefix',$prefix)
        ->where('c.company_id',$company_id)
        ->selectRaw("c.last_id,c.prefix,c.issue_year");
        if($issue_date) $qRow->where('c.issue_year',$year);
        $row = $qRow->first();

        $next_num = 0;
        if ($row){
            $next_num = $row->last_id;
            if($row->issue_year == $year) $year = $row->issue_year;
        }
        $next_num++;
        //example ref number => 2300001 || prefix-2300001
        $new_code = substr($year,-2) . Helper::formatNumber($next_num, $len);
        if($prefix) $new_code = $prefix.'-'.$new_code;
        $x = DB::table($target_tbl)->where('id', $newID)->update([$target_col => $new_code]);
        if ($x || $x === 1) {
            $Qupdated = DB::table($tbl_code_control)->where('branch_id', $branch_id)->where('prefix',$prefix);
            if($issue_date) $Qupdated->where('issue_year', $year);
            $updated = $Qupdated->where('company_id',$company_id)->update(['last_id' => $next_num]);
            $insert_arr = ['branch_id' => $branch_id, 'issue_year' => $year,'last_id' => $next_num,'company_id' => $company_id,'prefix'=>$prefix];
            if (!$updated) DB::table($tbl_code_control)->insert($insert_arr);
            // if ($onSuccess) $onSuccess();
            return (object)['status_code' => 200, 'status' => 'OK', 'code' => $new_code];
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
            // if()
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
                    $duplicate = UserBank::where('user_id', $userId)
                        ->where('bank_name', $bank['bank_name'])
                        ->where('currency', $bank['currency'])
                        ->where('id', '!=', $id)
                        ->exists();

                    if ($duplicate) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'This bank with the same currency already exists'
                        ]));
                    }
                    $userBank->update($bank);
                }else{
                    $accountCount = UserBank::where('user_id',$userId)->count();
                    if($accountCount == 2) return DataResponse::ValidateFail(__('messages.info',[
                        'info' => 'Only two accounts are allowed'
                    ]));
                    $duplicate = UserBank::where('user_id', $userId)
                        ->where('bank_name', $bank['bank_name'])
                        ->where('currency', $bank['currency'])
                        ->exists();

                    if ($duplicate) {
                        return DataResponse::ValidateFail(__('messages.info', [
                            'info' => 'This bank with the same currency already exists'
                        ]));
                    }
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
        if($user->has_account) return DataResponse::Duplicated('User ('.$user->username.') already has an account!');
        $validate = self::createLoginValidation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $loginName = $inputs['login_name'];
        $pwd = $inputs['password'];
        $cfPwd = $inputs['confirm_password'];
        $existLoginName = User::where('company_id',$authUser->company_id)
        ->where('id','!=',$userId)
        ->where('login_name',$loginName)
        ->where('is_deleted',0)
        ->where('account_type',$userClass)->first();
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
            'register_status' => 'registered',
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
        ->selectRaw('id,code,username,email,gender,shift_type,vehicle_type,plate_number,phone,national_id,lock')
        ->find($userId);
        if(!$user) return DataResponse::NotFound(__('messages.not_found',[
            'info' => $type
        ]));
        $msg = '';
        if($user->lock){
            $user->update([
                'lock' => false,
            ]);
            $msg = $type.' has turned on active mode';

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
            'info' => 'Account',
            'khInfo' => 'គណនី'
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
        $qU = User::where('is_deleted',0);
        if($type != 'all') $qU->where('account_type',$type);
        $user = $qU->find($id);
        if($user){
            $type = $user->account_type;
            if($user->system_admin) return DataResponse::Forbidden();
            if($user->account_type != 'admin'){
                $exists = Package::query()
                    ->from('packages as p')
                    ->where('p.is_deleted', 0)
                    ->where('p.driver_id', $user->id)
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('payment_packages as pp')
                            ->whereColumn('pp.package_id', 'p.id')
                            ->where('pp.payer_type', 'driver')
                            ->where('pp.is_deleted', false);
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('disbursement_packages as dp')
                            ->whereColumn('dp.package_id', 'p.id')
                            ->where('dp.payee_type', 'driver')
                            ->where('dp.is_deleted', false);
                    })
                    ->exists();

                if ($exists) {
                    return DataResponse::ValidateFail($type.' still has payment that is not paid.');
                }
                // $disKey = $type.'_disbursement_id';
                // $pmtKey = $type.'_payment_id';
                // $package = Package::where('is_deleted',0)->where('driver_id',$id)->where('outstanding',0)
                // ->selectRaw('id,driver_id,'.$disKey.','.$pmtKey)
                // ->first();
                // if($package) if(!$package->{$disKey} && !$package->{$pmtKey}) return DataResponse::ValidateFail($type.' still has payment that is not paid.');
                $payment = Payment::where('is_deleted',0)->where('payer_id',$id)->where('approved',0)->exists();
                $disbursement = Disbursement::where('is_deleted',0)->where('payee_id',$id)->where('approved',0)->where('type','payment')->exists();
                if($payment || $disbursement) return DataResponse::ValidateFail('Found some payments that are not paid yet!');
                if($type == 'driver' && self::hasCommission($id)) return DataResponse::ValidateFail($type.' still has commission to settle');
            }
            $user->update([
                'is_deleted' => true,
                'delete_datetime' => now(),
                'delete_uid' => $authUser->id
            ]);
        }

        return DataResponse::JsonResult(null,false,__('messages.deleted',[
            'info' => 'User'
        ]));
    }
    public static function changeLoginName($id,$newLoginName,$authUser){
        if(!$newLoginName) return DataResponse::ValidateFail(__('messages.info'),[
            'info' => 'Login name is required'
        ]);
        $user = User::where('is_deleted',0)->find($id);
        if(!$user) return DataResponse::NotFound(__('messages.not_found',[
            'info' => 'User'
        ]));
        $user->update([
            'login_name' => $newLoginName,
            'update_uid' => $authUser->id,
        ]);
        return DataResponse::JsonResult(null,false,__('messages.info',[
            'info' => 'New Login Name has been changed',
        ]));
    }

    private static function hasCommission($driverId){
        $qDc = DriverCommission::where('is_deleted',0)
        ->selectRaw('id,driver_id,delivery_type,pickup_commission,delivery_commission,delivery_commission_start_date,pickup_commission_start_date,DATE(updated_at) as updated_date')
        ->where('driver_id',$driverId);
        $driverCommissions = $qDc->get();
        $comm = TransactionService::getDriverCommissionInfo($driverCommissions,$driverId);
        $deliveryCommStartDate = $comm->normal_delivery_commission_start_date;
        $qP = Package::from('packages as p')
        ->selectRaw('p.status_id,p.driver_id')
        // ->whereIn('status_id',[9,19])
        ->where('p.status_id',9)
        ->where('p.driver_id',$driverId)
        ->where('p.is_deleted',0)
        ->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('disbursement_packages as dp')
                ->whereColumn('dp.package_id', 'p.id')
                ->where('dp.type','commission')
                ->where('dp.payee_type', 'driver')
                ->where('dp.is_deleted', false);
        });
        if($deliveryCommStartDate){
            $startDate = Helper::dateYMD($deliveryCommStartDate);
            $startDatetime = $startDate.' 00:00:00';
            // $qP->where(function($q) use ($startDatetime) {
            //     // $q->where(function($q) use ($startDatetime) {
            //     //     // For status_id 19, query only failed_datetime
            //     //     $q->whereDate('failed_datetime', '>=',$startDatetime)
            //     //     ->where('status_id', 19);
            //     // })
            //     $q->where(function($q) use ($startDatetime) {
            //         // For status_id 9, query only delivered_datetime
            //         $q->where('delivered_datetime', '>=' ,$startDatetime)
            //         ->where('status_id', 9);
            //     });
            // });
            $qP->where('p.delivered_datetime', '>=' ,$startDatetime);
        }
        $packages = $qP->get();

        // $qO = Order::where('is_deleted',0)->whereNull('driver_commission_id')
        // ->where('status_id',5)
        // ->where('driver_id',$driverId);
        // $orders = $qO->get();
        // $pkp = TransactionService::getPickUpDetails($orders,$driverId);
        $delPkg = TransactionService::getDeliveredDetails($packages,$driverId);

        $pickupRate = 0;//$comm->normal_pickup_commission;
        $deliveryRate = $comm->normal_delivery_commission;
        $totalDelivered = $delPkg->delivered_count;
        $totalPickUp = 0;//$pkp->total_package;
        // Log::error($deliveryCommStartDate);
        // Log::error($deliveryRate);
        // Log::error($totalDelivered);
        $total = Helper::getNumber($pickupRate * $totalPickUp + $deliveryRate * $totalDelivered,2);
        // Log::error($total);
        return $total > 0;
    }


    public function compareOtp(Request $req){
        $validator = validator($req->all(),[
            'otp' => 'required|string|min:6|max:6',
            'phone' => 'required|string:min:9|max:13'
        ]);
        if($validator->fails()) return ApiResponse::ValidateFail($validator->errors()->first());
        $inputs = $validator->validated();
        $otp = $inputs['otp'];
        $phone = $inputs['phone'];
    }

    public static function setUserTelegramBot(int $botId,int $userId, array $data,$userType='merchant'){
        $validator = validator($data,[
            'group_name' => 'required',
            'group_id' => 'required',
            'default_caption' => 'nullable|max:300'
        ]);
        if($validator->fails()){
            return DataResponse::ValidateFail($validator->errors()->first());
        }
        $inputs = $validator->validated();
        $tlBot = TelegramBot::where('is_deleted',false)->find($botId);
        if(!$tlBot){
            return DataResponse::NotFound(__('messages.not_found'));
        }
        $inputs['user_id'] = $userId;
        $inputs['update_uid'] = Auth::user()->id;
        $inputs['company_id'] = Auth::user()->company_id;
        $inputs['branch_id'] = Auth::user()->branch_id;
        $inputs['bot_id'] = $botId;
        $inputs['bot_token'] = $tlBot->token;
        $inputs['bot_name'] = $tlBot->name;
        $inputs['type'] = $userType;
        $ursTelegramBot = TelegramBotUser::where('is_deleted',false)->where('bot_id',$botId)->first();
        if(!$ursTelegramBot){
            $inputs['create_uid'] = Auth::user()->id;
            TelegramBotUser::create($inputs);
        }else{
            $ursTelegramBot->update($inputs);
        }
        $savedTelegramBotGroup = DB::table('telegram_bot_groups')
        ->where('bot_id',$botId)
        ->where('group_id',$inputs['group_id'])
        ->first();
        if(!$savedTelegramBotGroup){
            DB::table('telegram_bot_groups')->insert([
                'bot_id' => $botId,
                'group_name' => $inputs['group_name'],
                'group_id' => $inputs['group_id'],
                'create_uid' => Auth::user()->id,
                'update_uid' => $inputs['update_uid'],
                'company_id' => $inputs['company_id'],
                'branch_id' => $inputs['branch_id']
            ]);
        }else{
            $savedTelegramBotGroup = DB::table('telegram_bot_groups')
            ->where('bot_id',$botId)
            ->where('group_id',$inputs['group_id'])
            ->update([
                'group_name' => $inputs['group_name'],
            ]);
        }
        

        return DataResponse::JsonResult(null,false,__('messages.saved'));
    }

    public static function getUserTelegramBot(int $userId,$userType='merchant'){
        $user = User::where('account_type',$userType)->find($userId);
        if($user){
            $user->load(['telegramBot']);
            return DataResponse::JsonResult($user->telegramBot);
        }

        return DataResponse::NotFound();
    }
}
