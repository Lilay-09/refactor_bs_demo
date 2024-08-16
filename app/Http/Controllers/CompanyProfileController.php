<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Services\CompanyProfileService;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    //
    public function update(){
        $companyProfile = new CompanyProfileService();
        return ApiResponse::JsonRaw($companyProfile->update());
    }

    public function profile(){
        $companyProfile = new CompanyProfileService();
        return ApiResponse::JsonRaw($companyProfile->profile());
    }

    public function info(){
        $companyProfile = new CompanyProfileService();
         return ApiResponse::JsonRaw($companyProfile->info());
    }

}
