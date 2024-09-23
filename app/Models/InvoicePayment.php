<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    use HasFactory;
    protected $table = 'invoice_payments';
    protected $fillable = [
        'id',
        'invoice_id',
        'method',
        'bank_id',
        'amount_kh',
        'amount',
        'currency',
        'bank_number',
    ];
}
