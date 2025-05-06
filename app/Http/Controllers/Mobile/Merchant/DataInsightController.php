<?php

namespace App\Http\Controllers\Mobile\Merchant;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mobile\DataInsightService;
use App\Services\UserService;
use Illuminate\Http\Request;

class DataInsightController extends Controller
{
    //
    protected DataInsightService $dataInsight;
    protected $authUser;
    public function __construct(DataInsightService $dataInsightService){
        $this->dataInsight = $dataInsightService;
        $this->authUser = UserService::getAuthUser();
    }

    public function getDataInsight(Request $req){
        return ApiResponse::flex($this->dataInsight->getMerchantDataInsight($req,$this->authUser->id));
    }
}
