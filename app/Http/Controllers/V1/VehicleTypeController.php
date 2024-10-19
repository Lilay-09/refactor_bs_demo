<?php

namespace App\Http\Controllers\V1;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use App\Services\UserService;
use DataResponse;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    //

    public function vehicleTypeValiation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50'
        ]);
    }

    private function createOrUpdateVehicleType(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $vehicleType = null;
        if($id){
            $vehicleType = VehicleType::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
            if(!$vehicleType) return DataResponse::NotFound(__('messages.not_found',['info' => 'Vehicle type']));
        }
        $validate = $this->vehicleTypeValiation($req);
        if($validate->fails()) return DataResponse::ValidateFail($validate->errors()->first(),$validate->errors());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $updateOrCreate = null;
        if($vehicleType){ //** update */
            $existsType = VehicleType::where('name',$inputs['name'])->where('id','!=',$id)->first();
            if($existsType) return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'Product type ('.$inputs['name'].') is already exists.'
            ]));
            $updateOrCreate = $vehicleType->update($inputs);
        }else{
            //** create here */
            $inputs['create_uid'] = $user->id;
            $existsType = VehicleType::where('name',$inputs['name'])->first();
            if($existsType) return ApiResponse::Duplicated(__('messages.error',[
                'info' => 'Product type ('.$inputs['name'].') is already exists.'
            ]));
            $updateOrCreate = VehicleType::create($inputs);
        }

        if(!$updateOrCreate) return DataResponse::NotFound(__('messages.error',['info' => 'Error to save']));
        return DataResponse::JsonResult(null,false,__('messages.success',['info' => 'Saved']));
    }

    public function createVehicleType(Request $req){
        return ApiResponse::flex($this->createOrUpdateVehicleType($req));
    }

    public function updateVehicleType(Request $req){
        $id = $req->id;
        return ApiResponse::flex($this->createOrUpdateVehicleType($req,$id));
    }


    public function getVehicleTypes(Request $req){
        $user = UserService::getAuthUser();
        $vehicleTypes = VehicleType::where('company_id',$user->company_id)->where('is_deleted',0)->orderByDesc('id')->get();
        return ApiResponse::Pagination($vehicleTypes,$req);
    }

    public function getOneVehicleType(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $vehicleType = VehicleType::where('company_id',$user->company_id)->where('is_deleted',0)->find($id);
        if(!$vehicleType) return ApiResponse::NotFound(__('messages.not_found',['info' => 'Vehicle Type']));
        return ApiResponse::JsonResult($vehicleType,__('messages.get one'));
    }



    public function deleteVehicleType(Request $req){
        $user = UserService::getAuthUser();
        $id = $req->id;
        $productType = VehicleType::where('is_deleted',0)->where('company_id',$user->company_id)->find($id);
        if(!$productType) return ApiResponse::NotFound(__('messages.not_found'));
        $productType->update([
            'deleted_uid' => $user->id,
            'is_deleted' => 1,
            'deleted_datetime' => now()
        ]);
        return ApiResponse::JsonResult(null,__('messages.deleted',['info' => 'Vehicle type']));
    }
}
