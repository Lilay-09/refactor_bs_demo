<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    // use HasFactory;

    protected $table = 'payment_transactions';

    protected $casts = [
        'amount' => 'decimal:2',
        'details' => 'array',
    ];
    protected $fillable = [
        'id',
        'payment_date',
        'tran_via',
        'approved_uid',
        'payment_ref',
        'payment_id',
        'target_user',
        'transaction_type',
        'currency',
        'amount',
        'remarks',
        'from_account',
        'to_account',
        'from_account_number',
        'to_account_number',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid'
    ];

    public function disbursement(){
        return $this->belongsTo(Disbursement::class,'payment_id');
    }
    public function payment(){
        return $this->belongsTo(Payment::class,'payment_id');
    }

    public function performer(){
        return $this->belongsTo(User::class,'approved_uid');
    }

    public function approver(){
        return $this->belongsTo(User::class,'approved_uid');
    }
}
