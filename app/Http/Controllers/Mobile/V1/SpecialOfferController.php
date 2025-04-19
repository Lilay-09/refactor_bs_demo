<?php

namespace App\Http\Controllers\Mobile\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mobile\SpecialOfferService;
use App\Services\UserService;
use Illuminate\Http\Request;

class SpecialOfferController extends Controller
{
    //
    protected $specialOfferService;
    protected $authUser;
    public function __construct(SpecialOfferService $specialOfferService){
        $this->specialOfferService = $specialOfferService;
        $this->authUser = UserService::getAuthUser();
    }

    public function getSpecialOffers(Request $req){
        return ApiResponse::flex($this->specialOfferService->getSpecialOffers($req,$this->authUser,'merchant'));
    }
}
