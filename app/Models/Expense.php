<?php

namespace App\Models;

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
}
