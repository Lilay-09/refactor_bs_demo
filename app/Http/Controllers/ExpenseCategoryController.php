<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Services\UserService;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    //
    private function expenseCategoryValidation(Request $req){
        return validator($req->all(),[
            'name' => 'required|string:max:50',
            'name_kh' => 'nullable|string|max:250'
        ]);
    }

    public function createExpenseCategory(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->expenseCategoryValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $duplicateName = ExpenseCategory::where('name',$inputs['name'])->first();
        if($duplicateName) return ApiResponse::Duplicated('Category ('.$inputs['name'].') is already exists.');
        $create = ExpenseCategory::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function getExpenseCategories(Request $req){
        $user = UserService::getAuthUser();
        $rows = ExpenseCategory::where('branch_id',$user->branch_id)->get();
        return ApiResponse::Pagination($rows);
    }

    public function getExpenseCategory(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $id = $id ? $id : $req->id;
        $row = ExpenseCategory::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($row);
    }

    public function updateExpenseCategory(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $id = $id ? $id : $req->id;
        $expenseCategory = ExpenseCategory::where('branch_id',$user->branch_id)->find($id);
        if(!$expenseCategory) return ApiResponse::NotFound('Expense category not found');
        $validate = $this->expenseCategoryValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $duplicateName = ExpenseCategory::where('branch_id',$user->branch_id)->where('name',$inputs['name'])->where('id','!=',$id)->first();
        if($duplicateName) return ApiResponse::Duplicated('Category ('.$inputs['name'].') is already exists.');
        $update = $expenseCategory->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
