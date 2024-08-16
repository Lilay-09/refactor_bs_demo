<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderExpense extends Model
{
    use HasFactory;
    protected $table = 'purchase_order_expenses';

    protected $fillable = [
        'id',
        'purchase_order_id',
        'description',
        'expense_type',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
    ];
}
