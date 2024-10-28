<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CloudMessagingService;
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
}
