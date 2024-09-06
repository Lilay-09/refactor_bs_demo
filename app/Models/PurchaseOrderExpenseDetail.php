<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderExpenseDetail extends Model
{
    use HasFactory;
    protected $table = 'purchase_order_expense_details';
    protected $fillable = [
        'purchase_order_expense_id',
        'amount',
        'payment_method',
        'bank_number',
        'bank_id',
        'photo_file_name'
    ];
}
