<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPackage extends Model
{
    // use HasFactory;
    protected $table = 'payment_packages';

    protected $fillable = [
        'package_id',
        'payment_id',
        'type',
        'payer_type',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'deleted_reason'
    ];
}
