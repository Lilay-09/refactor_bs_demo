<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PoPaymentSlip extends Model
{
    use HasFactory;
    protected $table = 'po_payment_slips';
    protected $fillable = [
        'id',
        'photo_file_name',
        'purchase_id',
        'payment_id',
        'amount'
    ];
}
