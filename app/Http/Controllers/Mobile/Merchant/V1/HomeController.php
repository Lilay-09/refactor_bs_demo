<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\PickupCenterService;
use Helper;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    //

    public function createBooking(Request $req){
        $validate = validator($req->all(),[
            'vehicle_type' => 'required|string',
            'product_type' => 'nullable|string',
            'qty' => 'required|int|min:1',
            'loc_lat' => 'nullable|string',
            'loc_lng' => 'nullable|string',
            'pickup_address' => 'nullable|string',
            'details' => 'nullable|string'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $details = $inputs['details'] ?? null;
        if($details){
            $details = Helper::convertJsonTextToJson($details);
            if($details->error) return ApiResponse::ValidateFail($details->message);
            $pck = new PickupCenterService();
            foreach($details->result as $d){
                $dReq = new Request($d);
                $packageValidate = $pck->packageValidation($dReq);
                if($packageValidate->fails()) return ApiResponse::ValidateFail($packageValidate->errors()->first());
            }
        }
        return $inputs;
    }

    function getaddress($lat,$lng)
    {

    }
}
