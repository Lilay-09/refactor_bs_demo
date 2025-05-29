<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\UserNotificationService;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    //
    private object $authUser;
    public function __construct(private UserNotificationService $userNotificationService){
        $this->authUser = UserService::getAuthUser();
    }

    public function getUserNotificationSubscriptions(Request $req){
        return ApiResponse::flex($this->userNotificationService->getUserNotificationSubscriptions($req,$this->authUser));
    }
}
