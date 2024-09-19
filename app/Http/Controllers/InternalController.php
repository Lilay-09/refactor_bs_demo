<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MovementType;
use Illuminate\Http\Request;

class InternalController extends Controller
{
    //

    public function createMovementType(Request $req){
        $name = $req->name;
        $exists = MovementType::where('name',$name)->first();
        if($exists) return ApiResponse::Duplicated('Name is already taken.');
        $create = MovementType::create([
            'name' => $name
        ]);
        if(!$create) return ApiResponse::Error('Fail to create.');

        return ApiResponse::JsonResult(null,false,'Added');
    }
}
