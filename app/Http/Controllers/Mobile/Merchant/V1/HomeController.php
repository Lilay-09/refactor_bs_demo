<?php

namespace App\Http\Controllers\Mobile\Merchant\V1;

use App\Http\Controllers\Controller;
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
            'pickup_address' => 'nullable|string'
        ]);
    }

    function getaddress($lat,$lng)
    {

    }
}
