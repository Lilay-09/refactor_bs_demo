<?php

namespace App\Http\Controllers\Mobile\Driver\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mobile\DrawerService;
use App\Services\UserService;
use Illuminate\Http\Request;

class DrawerController extends Controller
{
    //
    private $drawerService;
    private $authUser;
    public function __construct(DrawerService $drawerService){
        $this->drawerService = $drawerService;
        $this->authUser = UserService::getAuthUser();
    }

    public function getUserZones(Request $req){

        return ApiResponse::flex($this->drawerService->getUserZone($this->authUser->id,$req->lang,'driver'));
    }
}
