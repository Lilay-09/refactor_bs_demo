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
        'trx_code',
        'payee_id',
        'payee_type',
        'amount',
        'currency_code',
        'cod_amount',
        'received_amount_usd',
        'received_amount_khr',
        'amount_due_usd',
        'amount_due_khr',
        'approved',
        'payable_amount',
        'delivery_fee',
        'pickup_rate',
        'delivery_rate',
        'fast_delivery_rate',
        'fast_pickup_rate',
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

    public function merchant(){
        return $this->belongsTo(User::class,'payee_id','id')->where('account_type','merchant');
    }

    public function cashier(){
        return $this->belongsTo(User::class,'approved_uid','id');
    }
    public function receiptionist(){
        return $this->belongsTo(User::class,'receiptionist_uid','id');
    }

    public function driver(){
        return $this->belongsTo(User::class,'payee_id','id')->where('account_type','driver');
    }
}
