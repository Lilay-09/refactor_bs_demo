<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $table = 'payments';
    protected $fillable = [
        'id',
        'payer_id',
        'payer_type',
        'amount',
        'currency_code',
        'cod_amount',
        'approved',
        'remarks',
        'package_count',
        'delivered_package_count',
        'exchange_rate',
        'breakdown_notes',
        'approve_uid',
        'receiver_uid',
        'payment_datetime',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
