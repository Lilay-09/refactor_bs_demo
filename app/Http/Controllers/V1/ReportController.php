<?php

namespace App\Http\Controllers;

use ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Services\UserService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    //
    public function getExpenseByCategory(Request $req){
        $user = UserService::getAuthUser();
        $expenses = ExpenseCategory::with(['expenses:id,category_id,updated_at,update_uid,amount,currency,expense_date,description','expenses.user:id,user_name,phone'])->where('branch_id',$user->branch_id)->get();
        foreach($expenses as $ep){
            foreach($ep->expenses as $e){
                $e->updated_user = $e->user->user_name;
                unset($e->user);
            }
            // unset($ep->user);
        }
        return ApiResponse::JsonResult($expenses);
    }

    public function getMonthlyExpense(Request $req){
        $expenses = Expense::selectRaw('TO_CHAR(expense_date, \'FMMonth\') as month,EXTRACT(YEAR FROM expense_date) as year,SUM(amount) as total,currency')
        ->groupByRaw('TO_CHAR(expense_date, \'FMMonth\'), EXTRACT(YEAR FROM expense_date),currency')
        ->get();
        $mergeExpense = [];
        foreach($expenses as $ep){
            $key = $ep->month.'-'.$ep->year;
            if(!isset($mergeExpense[$key])){
                $mergeExpense[$key] = [
                    'month' => $ep->month,
                    'year' => $ep->year,
                    'total' => $ep->total,
                    'currency' => $ep->currency
                ];
            }
            // $mergeExpense[$key]['list'] = $ep;
            unset($ep->month,$ep->year);
        }
        return ApiResponse::JsonResult(array_values($mergeExpense));
    }


    //** sales breakdown */
    public function SaleProduct(Request $req){
        $user = UserService::getAuthUser();
        $query = InvoiceItem::with(['invoice']);
        $query->whereHas('invoice',function ($query) use ($user){
            $query->where('company_id',$user->company_id);
        });
        $product = $query->get();
        $obj = (object)[
            'title' => 'Sale Product',
            'product' => $product,

        ];

        return ApiResponse::JsonResult($obj);
    }
}
