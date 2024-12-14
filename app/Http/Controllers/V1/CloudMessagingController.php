<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserNotificationToken;
use App\Services\CloudMessagingService;
use App\Services\UserService;
use Illuminate\Http\Request;

class CloudMessagingController extends Controller
{
    //
    protected $cloudService;
    public function __construct(CloudMessagingService $cms){
        $this->cloudService = $cms;
    }

    public function sendNoficationViaToken(Request $req){
        return ApiResponse::JsonRaw($this->cloudService->sendNotificationByToken($req));
    }

    public function subscribeToTopic(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonRaw($this->cloudService->subscribeTopic('admin',$req,$user));
    }

    public function sendNoficationViaTopic(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonRaw($this->cloudService->sendNotificationByTopic($req,$user));
    }

    public function unsubscribeFromTopic(Request $req){
        $user = UserService::getAuthUser();
        return ApiResponse::JsonRaw($this->cloudService->unsubscribeTopic($user,$req->topic,$req->token));
    }
    public function getUserToken(Request $req){
        $user = UserService::getAuthUser();
        $userToken = UserNotificationToken::orderByDesc('user_notification_tokens.id')->join('users as u','u.id','user_notification_tokens.user_id')->selectRaw('user_notification_tokens.id,user_notification_tokens.token,u.account_type,u.user_name,user_notification_tokens.os_name,user_notification_tokens.device_id')->get();
        return ApiResponse::JsonResult($userToken);
    }
}
