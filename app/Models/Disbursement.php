<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    use HasFactory;
    protected $table  = 'disbursements';
    protected $fillable = [
        'id',
        'payee_id',
        'payee_type',
        'amount',
        'currency_code',
        'cod_amount',
        'approved',
        'payable_amount',
        'delivery_fee',
        'pickup_rate',
        'delivery_rate',
        'type',
        'taxi_fee',
        'is_settled',
        'remarks',
        'package_count',
        'approved_datetime',
        'settled_datetime',
        'delivered_package_count',
        'failed_with_fee_count',
        'pickup_package_count',
        'receiptionist_uid',
        'exchange_rate',
        'breakdown_notes',
        'approved_uid',
        'settled_uid',
        'payment_datetime',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
