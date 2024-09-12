<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Models\CompanyProfile;
use App\Services\CompanyProfileService;
use App\Services\UserService;
use Helper;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    //
    function companyValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string|max:50',
            'name_km' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:250',
            'email' => 'nullable|email|max:100',
            // 'company_type' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:25',
            'description' => 'nullable|string|max:250',
            'photo' => 'nullable|string',
            'address_kh' => 'nullable|string|max:250',
            'remarks' => 'nullable|string|max:250',
            'cp_phone' => 'nullable|string|max:20',
            'cp_name' => 'nullable|string|max:35',
            'cp_email' => 'nullable|email|max:100',
        ]);
    }
    public function update(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->companyValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $company = CompanyProfile::find($user->company_id);
        if(!$company) return ApiResponse::NotFound('Company not found');
        $inputs['update_uid'] = $user->id;
        $photo = $inputs['photo'] ?? null;
        if(Helper::isValidBase64Image($photo) || !$photo){
            Helper::deleteImageFile($company->photo_file_name,$company->id,'company');
        }
        if($photo){
            $inputs['photo_file_name'] = Helper::base64ToImageFile($photo,$user->company_id,'company');
        }
        $update = $company->update($inputs);
        if(!$update && $inputs['photo_file_name']){
            Helper::deleteImageFile($inputs['photo_file_name'],$company->id,'company');
            return ApiResponse::Error('Fail to update company profile');
        }
        return ApiResponse::JsonResult(null,false,'Updated');

    }

    public function getCompanyProfile(Request $req){
        $user = UserService::getAuthUser();
        $companyProfile = CompanyProfile::find($user->company_id);
        if($companyProfile){
            $companyProfile->image_url = Helper::getImageUrl($companyProfile->photo_file_name,$companyProfile->id,'company');
        }
        return ApiResponse::JsonResult($companyProfile);
    }

}
