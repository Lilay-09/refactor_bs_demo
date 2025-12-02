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
        'payment_ref',
        'payment_status_id',
        'payee_id',
        'payee_type',
        'amount',
        'currency_code',
        'cod_amount',
        'approved',
        'payable_amount',
        'delivery_fee',
        'pickup_rate_type',
        'pickup_rate',
        'delivery_rate',
        'delivery_rate_type',
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
        'company_id',


        'received_amount_usd',
        'received_amount_khr',
        'amount_due_usd',
        'amount_due_khr',
        'requested_uid',
        'requested_date'
    ];

    public function merchant(){
        return $this->belongsTo(User::class,'payee_id','id')->where('account_type','merchant');
    }

    public function requestedUser(){
        return $this->belongsTo(User::class,'requested_uid','id');
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

    public function pmtPackages(){
        return $this->hasMany(DisbursementPackage::class,'disbursement_id');
    }

    public function disbursement(){
        return $this->belongsTo(Disbursement::class,'disbursement_id');
    }

    public function disbursementDetails(){
        return $this->hasMany(DisbursementDetails::class,'disbursement_id');
    }
}
