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
        'payable_amount',
        'delivery_fee',
        'taxi_fee',
        'is_settled',
        'remarks',
        'package_count',
        'received_datetime',
        'approved_datetime',
        'settled_datetime',
        'delivered_package_count',
        'exchange_rate',
        'breakdown_notes',
        'approved_uid',
        'settled_uid',
        'receiver_uid',
        'payment_datetime',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];

    public function cashier(){
        return $this->belongsTo(User::class,'settled_uid','id');
    }

    public function approvedUser($fkId='settled_uid'){
        return $this->belongsTo(User::class,$fkId,'id');
    }

}
