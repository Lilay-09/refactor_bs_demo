<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $table = 'payments';
    protected $fillable = [
        'id',
        'trx_code',
        'payer_id',
        'payer_type',
        'amount',
        'currency_code',
        'payment_status_id',
        'requested_date',
        'cod_amount',
        'received_amount_usd',
        'received_amount_khr',
        'amount_due_usd',
        'amount_due_khr',
        'requested_uid',
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
        'failed_with_fee_count',
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
        return $this->belongsTo(User::class,'approved_uid','id');
    }

    public function requestedUser(){
        return $this->belongsTo(User::class,'requested_uid','id');
    }
    
    public function driver(){
        return $this->belongsTo(User::class,'payer_id','id')->where('account_type','driver');
    }
    public function merchant(){
        return $this->belongsTo(User::class,'payer_id','id')->where('account_type','merchant');
    }

    public function approvedUser($fkId='settled_uid'){
        return $this->belongsTo(User::class,$fkId,'id');
    }

    public function paymentPackages(){
        return $this->hasMany(PaymentPackage::class,'payment_id')
        ->where('is_deleted',false);
    }

    public function pmtPackages(){
        return $this->hasMany(PaymentPackage::class,'payment_id');
    }
    public function paymentDetails(){
        return $this->hasMany(PaymentDetail::class,'payment_id');
    }


    public function transactionDriver(){
        return $this->hasOne(PaymentTransaction::class,'payment_id')->where('transaction_type',TransactionType::TRANSFER_IN->value)->where('target_user','driver');
    }

    public function transactionMerchant(){
        return $this->hasOne(PaymentTransaction::class,'payment_id')->where('transaction_type',TransactionType::TRANSFER_IN->value)->where('target_user','merchant');
    }

}
