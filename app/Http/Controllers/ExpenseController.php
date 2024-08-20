<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\UserService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    //
    private function expenseValidation(Request $req){
        return validator($req->all(),[
            'category_id' => 'required|int|exists:expense_categoies,id',
            'amount' => 'required|numeric',
            'expense_date' => 'required|date',
            'description' => 'nullable|string|max:250'
        ]);
    }

    public function createExpense(Request $req){
        $user = UserService::getAuthUser();
        $validate = $this->expenseValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['create_uid'] = $user->id;
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $create = Expense::create($inputs);
        if($create) return ApiResponse::JsonResult(null,false,'Created');
        return ApiResponse::Error('Fail to create');
    }

    public function getExpenses(Request $req){
        $user = UserService::getAuthUser();
        $rows = Expense::where('branch_id',$user->branch_id)->get();
        return ApiResponse::Pagination($rows);
    }

    public function getExpense(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $id = $id ? $id : $req->id;
        $row = Expense::where('branch_id',$user->branch_id)->find($id);
        return ApiResponse::JsonResult($row);
    }

    public function updateExpense(Request $req,$id=null){
        $user = UserService::getAuthUser();
        $id = $id ? $id : $req->id;
        $expense = Expense::where('branch_id',$user->branch_id)->find($id);
        if(!$expense) return ApiResponse::NotFound('Expense not found');
        $validate = $this->expenseValidation($req);
        if($validate->fails()) return ApiResponse::ValidateFail($validate->errors()->first());
        $inputs = $validate->validated();
        $inputs['update_uid'] = $user->id;
        $inputs['branch_id'] = $user->branch_id;
        $inputs['company_id'] = $user->company_id;
        $update = $expense->update($inputs);
        if($update) return ApiResponse::JsonResult(null,false,'Updated');
        return ApiResponse::Error('Fail to update');
    }
}
