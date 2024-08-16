<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductUomController extends Controller
{
    //
    public function createUom(Request $req){
        $validate = validator([
            'name' => $req->name,
            'name_kh' => $req->name_kh,
            'shortcut' => $req->shortcut
        ],[
            'name' => 'required|string|max:30',
            'name_kh' => 'nullable|string|max:100',
            'shortcut' => 'nullable|string|min:2|max:5'
        ]);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());

        $name = $req->name;
        $name_kh = $req->name_kh;
        $shortcut = $req->shortcut;
    }
}
