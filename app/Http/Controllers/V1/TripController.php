<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\TripService;
use App\Services\UserService;
use Illuminate\Http\Request;

class TripController extends Controller
{
    //
    public function __construct(
        private readonly TripService $tripService
    ){ }

    public function refreshTrip(Request $request){
        $authUser = UserService::getAuthUser();
        $this->tripService->refreshTripData($request->trip_id,$authUser);
        return ApiResponse::JsonResult(null,'Trip counts refreshed successfully');
    }
}
