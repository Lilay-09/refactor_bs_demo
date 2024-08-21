<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptPayment extends Model
{
    use HasFactory;
    protected $table = 'receipt_payments';
    protected $fillable = [
        'id',
        'receipt_id',
        'method',
        'bank_id',
        'amount',
        'bank_number',
    ];
}
