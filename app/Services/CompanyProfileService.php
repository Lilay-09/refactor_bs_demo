<?php

namespace App\Services;
use App\Models\Branch;
use App\Models\CompanyProfile;
use DataResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class CompanyProfileService
{
    // Your service methods go here
    public function update(){
        $user = JWTAuth::user();
        return $user;
    }

    public function profile(){
        return CompanyProfile::selectRaw('id,name,name_kh,address,email,phone,description')->first();
    }

    public function info(){
        $row = CompanyProfile::selectRaw('id,name,name_kh,address,email,phone,description')->first();
        if($row){
            $branches = self::companyBranches($row->id);
            $row->branches = $branches->list;
            $row->total_branches = $branches->total_branches;
        }
        return DataResponse::JsonResult($row);
    }

    public static function companyBranches($company_id){
        $rows = Branch::where('company_id',$company_id)->get();
        $total_branches = 0;
        foreach($rows as $row){
            $total_branches ++;
        }
        return (object)[
            'total_branches' => $total_branches,
            'list' => $rows
        ];
    }
}
