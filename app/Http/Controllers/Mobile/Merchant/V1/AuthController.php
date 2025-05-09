<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserShop;
use App\Services\AppSetting;
use App\Services\CloudMessagingService;
use App\Services\Mobile\AuthService;
use App\Services\UserService;
use App\Services\UserShopService;
use DB;
use Hash;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    //

    protected $userClass;
    public function __construct(){
        $this->userClass = 'merchant';
    }
    public function login(Request $request){
        $validate = validator([
            'username' => $request->username,
            'password' => $request->password
        ],[
            'username' => 'required|string',
            'password' => 'required|string|min:6|max:16',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());//DataResponse::ValidateFail($validate->errors());
        $input = $validate->validated();
        $account = $input['username'];
        $password = $input['password'];
        date_default_timezone_set('Asia/Phnom_Penh');
        $today = date('Y-m-d H:i:s');
        $user = User::where('account_type','merchant')->where('is_deleted',0)->where(function ($q) use ($account) {
            $q->where('email', $account)
            ->orWhere('phone', $account)
            ->orWhere('login_name', $account);
        })->selectRaw('photo_file_name,email,phone,id,password,system_admin,lock,company_id,account_type,login_name,delete_account,register_status,register_channel')->first();
        $systemAdmin = $user->system_admin ?? false;
        $isLock = $user->lock ?? false;
        if(!$user) return  ApiResponse::NotFound('Invalid Username or password');
        if($user->register_status !== 'registered' && $user->register_channel != 'admin') return ApiResponse::Unauthorized('You have no access to this application.');
        if($isLock || $user->delete_account) {
            if(!$systemAdmin) return ApiResponse::Unauthorized('You have no access to this application.');
        }
        if($user){
            if($user->account_type != $this->userClass && !$systemAdmin) return ApiResponse::Forbidden('You have no access to this application.');
            $user->roles = UserService::getRolesByUsers($user->id);
        }
        User::find($user->id)->update([
            'last_login' => $today
        ]);
        $credentials = [
            'password' => $password,
            'account_type' => $user->account_type,
        ];
        if($user->email == $account) $credentials['email'] = $account;
        else if($user->phone == $account) $credentials['phone'] = $account;
        else if($user->login_name == $account) $credentials['login_name'] = $account;

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::Unauthorized('Invalid Credentials');
        }
        try {
            $ttl = time() + (int)config('app.merchant_jwt_ttl');
            $token = JWTAuth::customClaims([
                'exp' => $ttl,
                'type' => 'access',
                'iss' => '',
            ])->fromUser($user);
            // if(!$token = JWTAuth::attempt($credentials)) {
            //     return ApiResponse::Unauthorized('invalid_credentials');
            // }
            // $token = JWTAuth::customClaims(['exp' => $ttl,'type'=>'access','iss' => ''])->fromUser($user);
        } catch (JWTException $e) {
            Log::error($e->getTraceAsString());
            return ApiResponse::Unauthorized();
        }
        // $refreshTokenFactory = JWTFactory::customClaims([
        //     'sub' => $user->id,
        //     'system_admin' => $user->system_admin,
        //     'roles' => $user->roles,
        //     'type' => 'refresh'
        // ])->setTTL(config('jwt.refresh_ttl'));
        // $refreshToken = JWTAuth::encode($refreshTokenFactory->make())->get();
        $data['token'] = $token;
        $data = (object)[];
        $data->id = $user->id;
        $data->name = $user->id;
        $data->user_name = $account;
        $data->profile = Helper::getImageUrl($user->photo_file_name,$user->company_id,'user_profile');
        $data->full_name = $user->first_name.' '.$user->last_login;
        $data->phone = $user->phone;
        // $data->roles = $user->roles;
        $data->token = $token;
        return ApiResponse::JsonResult($data, 'Success');
        // ->withCookie(cookie('session_', $token, config('jwt.ttl'), '/', null, false, false)->withSameSite('None'))
        // ->withCookie(cookie('token', $token, 0.5, '/', null, false, true)->withSameSite('None'))
        // ->withCookie(cookie('refresh_token', $refreshToken, config('jwt.refresh_ttl'), '/', null, false, true)->withSameSite('None'));
    }
    public function merchantRegistration(Request $req){

        $validate = validator($req->all(),[
            'phone' => 'required|string',
            'full_name' => 'nullable|string',
            'address' => 'nullable|string|max:250',
            'business_type' => 'nullable|string|max:50',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $phone = $inputs['phone'];
        //* Cache User information
        $existPhone = User::where('is_deleted',0)->where('account_type','merchant')->where('phone',$phone)->first();
        if($existPhone && $existPhone->register_status != 'in-progress') return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This phone number is already taken',
            'khInfo' => 'លេខទូរស័ព្ទនេះបានប្រើរួច'
        ]));
        $maxAttempts = 50; // Maximum allowed attempts
        $lockoutTime = 3600; // Lockout duration in seconds (60 minute)

        // Check if the user is locked out
        if (Cache::has("login_attempts:locked:{$phone}")) {
            // return response()->json(['error' => 'Too many login attempts. Please try again later.'], 429);
            return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'too many login attempts. Please try again later'
            ]));
        }

        // return Cache::get('login_attempts:locked:{$phone}');
        // Increment the login attempt count
        $attempts = Cache::increment("login_attempts:{$phone}");
        if ($attempts === 1) {
            // Set an expiration for the attempts count
            Cache::put("login_attempts:{$phone}", $attempts, $lockoutTime);
        }

        // If the user exceeds max attempts, lock them out
        if ($attempts >= $maxAttempts) {
            Cache::put("login_attempts:locked:{$phone}", true, $lockoutTime);
             return ApiResponse::ValidateFail(__('messages.info',[
                'info' => 'too many login attempts. Please try again later'
            ]));
        }

        $otp = Helper::newOTP();

        $newReq = new Request([
            'user_name' => $inputs['full_name'] ?? null,
            'phone' => $phone,
            'address' => $inputs['address'] ?? null,
            'account_type' => 'merchant',
            'business_type' => $inputs['business_type'] ?? null,
            'otp' => $otp,
            'register_channel' => 'mobile',
            'cod' => true
        ]);

        $authUser = User::where('system_admin',1)->selectRaw('id,company_id,branch_id')->first();
        $createUser = UserService::createOrUpdateUser($newReq,'merchant',$authUser,$existPhone?->id,true);
        if($createUser->error) return ApiResponse::flex($createUser);
        $message = __('messages.info',[
                'info' => 'Your otp '.$otp,
                'khInfo' => 'លេខសំងាត់ '.$otp
        ]);

        $smsInfo = AppSetting::sendSms(config('app.plasgate_sender'),$phone,$message);
        if($smsInfo->error) return ApiResponse::ValidateFail('Error sending SMS, Please try again later.');
        return ApiResponse::JsonResult([
            'phone' => $phone,
        ],'Registered');
    }

    public function registrationPassword(Request $req){
        $validate = validator($req->all(),[
            'phone' => 'required|string',
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $phone = $inputs['phone'];
        $pwd = $inputs['password'];
        $cfPwd = $inputs['confirm_password'];
        $found = User::where('is_deleted',0)->where('phone',$phone)->where('account_type','merchant')->first();
        if(!$found) return ApiResponse::NotFound();
        if($pwd !== $cfPwd) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Password not match !','khInfo' => 'លេខសំងាត់មិនត្រូវគ្នា']));
        $hpwd = Hash::make($pwd);
        if($found->otp) return ApiResponse::ValidateFail(__('messages.info',['info' => 'Failed']));
        if($found->has_account) return ApiResponse::Duplicated();
        $found->update([
            'user_name' => $inputs['username'],
            'login_name' => $phone,
            'password' => $hpwd,
            'has_account' => true,
            'register_status' => 'registered',
            'lock' => false,
        ]);
        return ApiResponse::JsonResult(null,'Success');
    }

    public function forgetPassword(Request $req){
        $phone = $req->phone;
        $found = User::where('is_deleted',0)->where('phone',$phone)
        ->where('account_type','merchant')
        ->where('register_status','registered')
        ->where('lock',false)
        ->first();
        if(!$found) {
            return ApiResponse::ValidateFail(__('messages.not_found'));
        }
        // return $found;
        $otp = Helper::newOTP();
        $validPhone = Helper::formatPhoneNumber($phone);
        $found->update([
            'otp' => $otp
        ]);
        $otpContent = __('messages.info',[
                'info' => 'Your otp '.$otp,
                'khInfo' => 'លេខសំងាត់ '.$otp
        ]);
        $otpSend = AppSetting::sendSms(config('app.plasgate_sender'),$validPhone,$otpContent);
        if($otpSend->error){
            return ApiResponse::ValidateFail(__('messages.try_again'));
        }
        return ApiResponse::JsonResult(null,'sent');
    }

    public function forgotPasswordReset(Request $req){
        $validate = validator($req->all(),[
            'phone' => 'required|string',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|min:6'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $phone = $inputs['phone'];
        $pwd = $inputs['password'];
        $cfPwd = $inputs['confirm_password'];
        $found = User::where('is_deleted',0)->where('phone',$phone)->where('account_type','merchant')->first();
        if(!$found) return ApiResponse::NotFound();
        if($pwd !== $cfPwd) return ApiResponse::ValidateFail(__('messages.error',['info' => 'Password not match !','khInfo' => 'លេខសំងាត់មិនត្រូវគ្នា']));
        // $hpwd = Hash::make($pwd);
        if($found->otp) {
            return ApiResponse::ValidateFail(__('messages.info',['info' => 'Failed']));
        }
        // Log::info('Old password: ' . $found->getOriginal('password'));
        $found->password = Hash::make($pwd);
        $found->save();
        return ApiResponse::JsonResult(null,'Success');
    }

    public function verifyOTP(Request $req){
        return ApiResponse::flex(UserService::verifyOTP($req,'merchant'));
    }

    public function resendOtp(Request $req){
        $phone = $req->phone;
        $validPhone = Helper::formatPhoneNumber($phone);
        $user = User::where('phone',$phone)->where('account_type','merchant')->orderByDesc('id')->select('id','otp')->first();
        $otp = Helper::newOTP();
        $message = __('messages.info',[
                'info' => 'Your otp '.$otp,
                'khInfo' => 'លេខសំងាត់ '.$otp
        ]);
        $user->update([
            'otp' => $otp
        ]);
        // return $validPhone;
        return ApiResponse::flex(AppSetting::sendSms(config('app.plasgate_sender'),$validPhone,$message));
    }

    public function subscribeTopics(Request $req){
        $user = UserService::getAuthUser($this->userClass);
        $cldMsgService = new CloudMessagingService();
        return $cldMsgService->subscribeTopic($this->userClass,$req,$user);
    }

    public function getProfile(Request $req){
        $user = UserService::getAuthUser('merchant');
        $authService = new AuthService();
        $profile = $authService->getProfile($user,'merchant');
        $userShop = UserShop::where('owner_id',$user->id)
        ->select('name_en','est_pcs','city','commune')->first();
        $profile->data['shop_name'] = $userShop->name_en ?? '';
        $profile->data['est_pcs'] = (int) ($userShop->est_pcs ?? 0);
        $profile->data['city'] = $userShop->city ?? '';
        $profile->data['commune'] = $userShop->commune ?? '';
        return ApiResponse::flex($profile);
    }

    public function updateProfile(Request $req){
        $authUser = UserService::getAuthUser('merchant');
        $validate = validator($req->all(),[
            'user_name' => 'required|string',
            'email' => 'nullable|string',
            'address' => 'nullable|string',
            'photo' => 'nullable',
            'pin_address' => 'nullable',
            'loc_lat' => 'nullable',
            'loc_lng' => 'nullable',
        ]);

        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = User::where('account_type',$authUser->account_type)->selectRaw('id,photo_file_name,user_name,phone,email,company_id,branch_id')->find($authUser->id);
        $inputs = $validate->validated();
        $photo = $inputs['photo'] ?? null;
        if($photo instanceof UploadedFile){
            $inputs['photo_file_name'] = Helper::saveImageFile($photo,$authUser->company_id,'user_profile')->filename;
            Helper::deleteImageFile($user->photo_file_name,$authUser->company_id,'user_profile');
        }else if(!$photo) Helper::deleteImageFile($user->photo_file_name,$authUser->company_id,'user_profile');
        $inputs['latitude'] = $inputs['loc_lat'] ?? null;
        $inputs['longitude'] = $inputs['loc_lng'] ?? null;
        $errorMsg = '';
        DB::transaction(function () use($user,$inputs,$req,&$errorMsg){
            $user->update($inputs);
            $userShopService = new UserShopService();
            $shopReq = clone $req;
            $shopReq->merge([
                'name_en' => $req->shop_name ?? null,
                'owner_id' => $user->id,
                'phone' => $user->phone,
                'est_pcs' => $req->est_pcs,
                'city' => $shopReq->city,
                'district' => $shopReq->district
            ]);
            // Log::info($shopReq->all());
            $shop = $userShopService->saveShop($shopReq,$user);
            if($shop->error){
                $errorMsg = $shop->message;
            }
        });
        if($errorMsg){
            return ApiResponse::Error($errorMsg);
        }
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Updated'
        ]));
    }

    public function resetPassword(Request $req){
        $user = UserService::getAuthUser('merchant');
        $resetPass = UserService::resetPassword($req,$user->id,$this->userClass);
        return ApiResponse::flex($resetPass);
    }

    public function deleteAccount(Request $req){
        $user = UserService::getAuthUser('merchant');
        $deleteAcc = UserService::deleteUserAccount($user->id,'merchant');
        return ApiResponse::flex($deleteAcc);
    }
    public function logOut(Request $req){
        $user = UserService::getAuthUser('merchant');
        $deleteAcc = UserService::logOut($req,$user);
        return ApiResponse::flex($deleteAcc);
    }
}
