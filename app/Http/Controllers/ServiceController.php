<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InvoiceSerivce;
use App\Models\ReceiptService;
use App\Models\Service;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    protected static $imgDir = 'service';
    //
    public function serviceValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string:max:50',
            'name_kh' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:250',
            'price' => 'required|numeric',
            'photo'=> 'nullable|string'
        ]);
    }

    public function createService(Request $req){
        $validate = $this->serviceValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $user = UserService::getAuthUser();
        $name = $inputs['name'];
        // $name_kh = $inputs['name_kh'];
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $photo = $inputs['photo'] ?? null;
        unset($inputs['photo']);
        $duplicateName = Service::where('branch_id',$user->branch_id)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Category ('.$name.') is already exists.');
        $photoFile = Helper::base64ToImageFile($photo,$user->company_id,self::$imgDir);
        if($photoFile) $inputs['photo_file_name'] = $photoFile;
        $create = Service::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        Helper::deleteImageFile($photoFile,$user->company_id,self::$imgDir);
        return ApiResponse::Error('Fail to create');
    }

    public function getServices(Request $req){
        $user = UserService::getAuthUser();
        $query = Service::where('branch_id',$user->branch_id)->selectRaw('id,name,name_kh,photo_file_name');
        if($req->search){
            $query->where('name','ilike','%'.$req->search.'%')->orWhere('name_kh','ilike','%'.$req->search.'%');
        }
        $services = $query->get();
        foreach($services as $service){
            $service->image_url = Helper::getFileUrl($service->photo_file_name,$user->company_id,self::$imgDir);
            unset($service->photo_file_name);
        }
        return ApiResponse::Pagination($services,$req);
    }

    public function getService(Request $req,$id=null){
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $service = Service::where('branch_id',$user->branch_id)->find($id);
        if($service) $service->image_url = Helper::getFileUrl($service->photo_file_name,$user->company_id,self::$imgDir);
        return ApiResponse::JsonResult($service);
    }

    // public function createSubCategory(){

    // }


    public function updateService(Request $req,$id=null){
        $validate = $this->serviceValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $id = $id ? $id : $req->id;
        $user = UserService::getAuthUser();
        $serivce = Service::where('branch_id',$user->branch_id)->find($id);
        if(!$serivce) return ApiResponse::NotFound('Service not found');
        $name = $inputs['name'];
        // $name_kh = $inputs['name_kh'] ?? null;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $photo = $inputs['photo'] ?? null;
        $duplicateName = Service::where('company_id',$user->company_id)->where('id','!=',$id)->where('name',$name)->first();
        if($duplicateName) return ApiResponse::Duplicated('Service ('.$name.' is already exists.)');
        if(Helper::isValidBase64Image($photo) || !$photo){
            Helper::deleteImageFile($serivce->photo_file_name,$user->company_id,self::$imgDir);
            $photoFile = Helper::base64ToImageFile($photo,$user->company_id,self::$imgDir);
            if($photoFile) $inputs['photo_file_name'] = $photoFile;
        }
        $update = $serivce->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }

    public function deleteService(Request $req){
        $id = $req->id;
        $user = UserService::getAuthUser();
        $service = Service::where('branch_id',$user->branch_id)->find($id);
        if($service){
            $usedInReceipt = ReceiptService::where('service_id',$id)->first();
            $useInInvoice = InvoiceSerivce::where('service_id',$id)->first();
            if($usedInReceipt || $useInInvoice) return ApiResponse::ValidateFail('To keep history record, you cannot delete the used service');
            Helper::deleteImageFile($service->photo_file_name,$user->company_id,self::$imgDir);
            $service->delete();
            return ApiResponse::JsonResult(null);
        }
        return ApiResponse::NotFound('Service not found');
    }
}
