<?php

namespace App\Models;

use Helper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;
    protected $table = 'expenses';
    protected $fillable = [
        'id',
        'amount',
        'currency',
        'expense_date',
        'description',
        'category_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];

    public function getCategory(){
        return $this->belongsTo(ExpenseCategory::class,'category_id','id');
    }

    protected $casts = [
        'updated_at' => 'date:d-M-Y',
        'expense_date' => 'date:d-m-Y'
    ];

    public function user(){
        return $this->belongsTo(User::class,'update_uid','id')->select('user_name','phone','id');
    }
}
