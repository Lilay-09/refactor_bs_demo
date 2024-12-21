<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CloudMessagingService;
use App\Services\Mobile\AuthService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

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
        $user = User::where('account_type','driver')->where(function ($q) use ($account) {
            $q->where('email', $account)
            ->orWhere('phone', $account)
            ->orWhere('login_name', $account);
        })->selectRaw('photo_file_name,email,phone,id,system_admin,lock,company_id,account_type,login_name,delete_account')->first();
        $systemAdmin = $user->system_admin ?? false;
        $isLock = $user->lock ?? false;
        if(!$user) return  ApiResponse::NotFound('Invalid Username or password');
        // return $user;
        if($isLock || $user->delete_account) {
            if(!$systemAdmin) return ApiResponse::Unauthorized('You have no access to this application.');
        }

        if($user){
            if($user->account_type != 'driver') return ApiResponse::Forbidden('You have no access to this application.');
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
        try {
            $ttl = (int)env('DRIVER_JWT_TTL');
            JWTAuth::factory()->setTTL($ttl);
            if(!$token = JWTAuth::attempt($credentials)) {
                return ApiResponse::Unauthorized('invalid_credentials');
            }
            $token = JWTAuth::customClaims(['system_admin' => $user->system_admin,'roles'=>$user->roles,'type'=>'access','account_type' => $user->account_type])->fromUser($user);
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
        $user = UserService::getAuthUser('driver');
        $authService = new AuthService();
        return ApiResponse::flex($authService->getProfile($user));
    }

    public function updateProfile(Request $req){
        $authUser = UserService::getAuthUser('driver');
        $validate = validator($req->all(),[
            'user_name' => 'required|string',
            'email' => 'nullable|string',
            'address' => 'nullable|string',
            'photo' => 'nullable'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = User::where('account_type',$authUser->account_type)->selectRaw('id,photo_file_name,user_name,phone,email')->find($authUser->id);
        $inputs = $validate->validated();
        $photo = $inputs['photo'] ?? null;
        if($photo instanceof UploadedFile){
            $inputs['photo_file_name'] = Helper::saveImageFile($photo,$authUser->company_id,'user_profile')->filename;
            Helper::deleteImageFile($user->photo_file_name,$authUser->company_id,'user_profile');
        }else if(!$photo) Helper::deleteImageFile($user->photo_file_name,$authUser->company_id,'user_profile');
        $user->update($inputs);
        return ApiResponse::JsonResult(null,__('messages.info',[
            'info' => 'Updated'
        ]));
    }

    public function subscribeTopics(Request $req){
        $user = UserService::getAuthUser('driver');
        $cldMsgService = new CloudMessagingService();
        return $cldMsgService->subscribeTopic('driver',$req,$user);
    }

    public function unsubscribeTopics(Request $req){
        $user = UserService::getAuthUser('driver');
        $cldMsgService = new CloudMessagingService();
        return $cldMsgService->unsubscribeAllTopics($user);
    }

    public function resetPassword(Request $req){
        $user = UserService::getAuthUser('driver');
        $resetPass = UserService::resetPassword($req,$user->id,'driver');
        return ApiResponse::flex($resetPass);
    }

    public function deleteAccount(Request $req){
        $user = UserService::getAuthUser('driver');
        $deleteAcc = UserService::deleteUserAccount($user->id,'driver');
        return ApiResponse::flex($deleteAcc);
    }

    public function logOut(Request $req){
        $user = UserService::getAuthUser('driver');
        $deleteAcc = UserService::logOut($req,$user);
        return ApiResponse::flex($deleteAcc);
    }
}
