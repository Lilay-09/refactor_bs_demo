<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;
    protected $table = 'expense_categories';
    protected $fillable = [
        'id',
        'name',
        'name_kh',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];

    public function expenses(){
        return $this->hasMany(Expense::class,'category_id','id');
    }

    protected $casts = [
        'updated_at' => 'date:d-M-Y'
    ];

}
