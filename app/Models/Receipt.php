<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;
    protected $table = 'receipts';
    protected $fillable = [
        'ref_code',
        'tax',
        'receipt_date',
        'invoice_id',
        'customer_id',
        'customer_phone',
        'total_amount',
        'due_amount',
        'paid_amount',
        'discount_percent',
        'discount_amount',
        'currency_rate',
        'general',
        'remarks',
        'currency',
        'stock_location_id',
        'invoice_id',
        'branch_id',
        'create_uid',
        'update_uid',
        'company_id'
    ];
}
