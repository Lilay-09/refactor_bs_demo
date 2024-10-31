<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\AuthService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;
use Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    //
    public function login(Request $request){
        $validate = validator([
            'username' => $request->username,
            'password' => $request->password
        ],[
            'username' => 'required|string',
            'password' => 'required|string|min:6|max:16',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->all());//DataResponse::ValidateFail($validate->errors());
        $input = $validate->validated();
        $account = $input['username'];
        $password = $input['password'];
        date_default_timezone_set('Asia/Phnom_Penh');
        $today = date('Y-m-d H:i:s');
        $user = User::where('email',$account)->orWhere('phone',$account)->orWhere('login_name',$account)->selectRaw('photo_file_name,email,phone,id,system_admin,lock,company_id,account_type,login_name')->first();
        $systemAdmin = $user->system_admin ?? false;
        $isLock = $user->lock ?? false;
        if($isLock) {
            if(!$systemAdmin) return ApiResponse::Unauthorized('You have no access to this application.');
        }
        if(!$user) return  ApiResponse::NotFound('Invalid Username or password');
        if($user){
            if($user->account_type != 'merchant' && !$systemAdmin) return ApiResponse::Forbidden('You have no access to this application.');
            $user->roles = UserService::getRolesByUsers($user->id);
        }
        User::find($user->id)->update([
            'last_login' => $today
        ]);
        $credentials = [
            'password' => $password
        ];
        if($user->email == $account) $credentials['email'] = $account;
        else if($user->phone == $account) $credentials['phone'] = $account;
        else if($user->login_name == $account) $credentials['login_name'] = $account;
        try {
            $ttl = (int)env('MERCHANT_JWT_TTL');
            JWTAuth::factory()->setTTL($ttl);
            if(!$token = JWTAuth::attempt($credentials)) {
                return ApiResponse::Unauthorized('invalid_credentials');
            }
            $token = JWTAuth::customClaims(['system_admin' => $user->system_admin,'roles'=>$user->roles,'type'=>'access'])->fromUser($user);
        } catch (JWTException $e) {
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


    public function getProfile(Request $req){
        $user = UserService::getAuthUser('merchant');
        $authService = new AuthService();
        return ApiResponse::flex($authService->getProfile($user));
    }

    public function merchantRegistration(Request $req){
        $validate = validator($req->all(),[
            'phone' => 'required|string',
            'full_name' => 'required|string',
            'address' => 'nullable|string|max:250',
            'business_type' => 'nullable|string|max:50',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $phone = $inputs['phone'];
        //* Cache User information
        $existPhone = User::where('is_deleted',0)->where('account_type','merchant')->where('phone',$phone)->first();
        if($existPhone) return ApiResponse::Duplicated(__('messages.info',[
            'info' => 'This phone number is already taken',
            'khInfo' => 'លេខទូរស័ព្ទនេះបានប្រើរួច'
        ]));
        // User::create([
        //     'user_name' => $inputs['full_name'],
        //     'phone' => $phone,
        //     'address' => $inputs['address'] ?? null,
        //     'business_type' => $inputs['business_type'] ?? null,
        // ]);
        $otp = Helper::newOTP();
        $message = __('messages.info',[
                'info' => 'Your otp '.$otp,
                'khInfo' => 'លេខសំងាត់ '.$otp
        ]);
        return ApiResponse::JsonResult([
            'phone' => $phone,
        ],$message);
    }

    public function registrationPassword(){

    }

    public function verifyOTP(Request $req){
        $validate = validator($req->all(),[
            'phone' => 'required|string',
            'otp' => 'required|string|min:6|max:6',
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $otp = $inputs['phone'];
        $phone = $inputs['phone'];
        $user = User::where('is_deleted',0)->where('account_type','merchant')->where('phone',$phone)->first();
        $validOtp = $user->otp;
        if($otp != $validOtp) return ApiResponse::ValidateFail(__('messages.info',[
            'info' => 'Incorrect otp',
            'khInfo' => 'លេខផ្ទៀងផ្ទាត់មិនត្រូវ'
        ]));


    }
}
