<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PriceListController extends Controller
{
    //

    public function priceListValidation(Request $req){
        return validator($req->all(),[

        ]);
    }
}
