<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
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
        return ApiResponse::JsonRaw($this->cloudService->subscribeTopic('web',$req->token,$user));
    }

    public function sendNoficationViaTopic(Request $req){
        return ApiResponse::JsonRaw($this->cloudService->sendNotificationByTopic($req));
    }

}
