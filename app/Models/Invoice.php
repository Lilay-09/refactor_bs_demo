<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $table = 'invoices';
    protected $fillable = [
        'id',
        'ref_code',
        'tax',
        'issue_date',
        'customer_id',
        'customer_phone',
        'due_date',
        'total_amount',
        'due_amount',
        'default_discount',
        'paid_amount',
        'discount_percent',
        'discount_amount',
        'exchange_rate',
        'remarks',
        'currency',
        'stock_location_id',
        'status_id',
        'branch_id',
        'create_uid',
        'update_uid',
        'company_id'
    ];
}
