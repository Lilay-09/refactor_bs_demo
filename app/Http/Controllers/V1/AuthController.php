<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;
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
        $user = User::where('email',$account)->orWhere('phone',$account)->selectRaw('photo_file_name,email,phone,id,system_admin,lock,company_id,account_type')->first();
        $systemAdmin = $user->system_admin ?? false;
        $isLock = $user->lock ?? false;

        if($isLock) {
            if(!$systemAdmin) return ApiResponse::Unauthorized('You have no access to this application.');
        }
        if(!$user) return  ApiResponse::NotFound('Invalid Username or password');
        if($user){
            if($user->account_type != 'admin') return ApiResponse::Forbidden('You have no access to this application.');
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
        try {
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
        // $data['token'] = $token;
        $data = (object)[];
        $data->id = $user->id;
        $data->name = $user->id;
        $data->user_name = $account;
        $data->profile = Helper::getImageUrl($user->photo_file_name,$user->company_id,'user_profile');
        $data->full_name = $user->first_name.' '.$user->last_login;
        $data->phone = $user->phone;
        $data->roles = $user->roles;
        $data->token = $token;
        // return response()->json([
        //     'status_code' => 200,
        //     'data' => $data,
        // ],200);
        return ApiResponse::JsonResult($data,false,'Success');
        // ->withCookie(cookie('session_', $token, config('jwt.ttl'), '/', null, true, false)->withSameSite('None'))
        // ->withCookie(cookie('access_token', $token, config('jwt.ttl'), '/', null, true, true)->withSameSite('None'))
        // ->withCookie(cookie('refresh_token', $refreshToken, config('jwt.refresh_ttl'), '/', null, true, true)->withSameSite('None'));
    }
}
