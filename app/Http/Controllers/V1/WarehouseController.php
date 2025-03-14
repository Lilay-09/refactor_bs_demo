<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\UserService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    //

    public function updateWarehouse(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $warehouse = Warehouse::where('company_id',$user->company_id)->find($id);
        if(!$warehouse) return ApiResponse::NotFound();
        $warehouse->update([
            'address' => $req->address,
            'cp_phone' => $req->cp_phone,
            'cp_name' => $req->cp_name,
        ]);

        return ApiResponse::JsonResult(null,'Updated');
    }
}
