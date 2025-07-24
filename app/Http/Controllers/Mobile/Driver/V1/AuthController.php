<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CloudMessagingService;
use App\Services\Mobile\AuthService;
use App\Services\UserService;
use DB;
use Hash;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());//DataResponse::ValidateFail($validate->errors());
        $input = $validate->validated();
        $account = $input['username'];
        $password = $input['password'];
        // \Log::info('sdf');
        date_default_timezone_set('Asia/Phnom_Penh');
        $today = date('Y-m-d H:i:s');
        $user = User::where('account_type','driver')->where('is_deleted',0)->where(function ($q) use ($account) {
            $q->where('email', $account)
            // ->orWhere('phone', $account)
            ->orWhere('login_name', $account);
        })->selectRaw('code,photo_file_name,username,email,phone,id,password,system_admin,lock,company_id,account_type,login_name,delete_account')->first();
        if(!$user) return  ApiResponse::NotFound('Invalid Username or Password');
        $systemAdmin = $user->system_admin ?? false;
        $isLock = $user->lock ?? false;
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

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::Unauthorized('Invalid Credentials');
        }
        try {
            $ttl = time() + (int)config('app.driver_jwt_ttl');
            $token = JWTAuth::customClaims([
                'exp' => $ttl,
                'type' => 'access',
                'iss' => '',
            ])->fromUser($user);
            // if(!$token = JWTAuth::attempt($credentials)) {
            //     return ApiResponse::Unauthorized('Invalid Username or Password');
            // }
            // $token = JWTAuth::customClaims(['exp'=>$ttl,'type'=>'access','iss' => ''])->fromUser($user);
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
        $data->code = $user->code;
        $data->name = $user->id;
        $data->username = $user->username;
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
        return ApiResponse::flex($authService->getProfile($user,'driver'));
    }

    public function updateProfile(Request $req){
        $authUser = UserService::getAuthUser('merchant');
        $validate = validator($req->all(),[
            'username' => 'required|string',
            'email' => 'nullable|string',
            'address' => 'nullable|string',
            'phone_1' => 'nullable|string',
            'phone_2' => 'nullable|string',
            'phone_3' => 'nullable|string',
            'photo' => 'nullable',
            'pin_address' => 'nullable',
            'loc_lat' => 'nullable',
            'loc_lng' => 'nullable'
        ]);
        // \Log::error(json_encode($req->all()));
        // Log::info($req->all());
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $user = User::where('account_type',$authUser->account_type)
        ->selectRaw('id,photo_file_name,username,phone,email,pin_address,latitude,longitude')
        ->find($authUser->id);
        $inputs = $validate->validated();
        $inputs['latitude'] = $inputs['loc_lat'] ?? null;
        $inputs['longitude'] = $inputs['loc_lng'] ?? null;
        $photo = $inputs['photo'] ?? null;
        if(!empty($inputs['phone1'])){
            $inputs['phone'] = $inputs['phone1'];
        }
        $phones = [];

        if (!empty($inputs['phone2'])) {
            $phones[] = $inputs['phone2'];
        }

        if (!empty($inputs['phone3'])) {
            $phones[] = $inputs['phone3'];
        }

        $maxSize = Helper::validTotalImageSize([$photo]);
        if($maxSize->error) return ApiResponse::ValidateFail($maxSize->message);
        if($photo instanceof UploadedFile){
            $isValidUpload = Helper::isValidUploadImage($photo,1);
            if($isValidUpload->error) return ApiResponse::ValidateFail($isValidUpload->message);
            $inputs['photo_file_name'] = Helper::saveImageFile($photo,$authUser->company_id,'user_profile')->filename;
            Helper::deleteImageFile($user->photo_file_name,$authUser->company_id,'user_profile');
        }else if(!$photo) Helper::deleteImageFile($user->photo_file_name,$authUser->company_id,'user_profile');
        $user->update($inputs);
        // Log::info($otherPhoneLines);
        foreach ($phones as $phone) {
            DB::table('user_contacts')->updateOrInsert(
                ['user_id' => $user->id, 'phone' => $phone], // unique constraint
                ['updated_at' => now()] // fields to update
            );
        }
        return ApiResponse::JsonResult([
            'image_url' => Helper::getImageUrl($user->photo_file_name,$authUser->company_id,'user_profile')
        ],__('messages.info',[
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
