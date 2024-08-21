<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSerivce extends Model
{
    use HasFactory;
    protected $table = 'invoice_services';
    protected $fillable = [
        'id',
        'service_id',
        'invoice_id',
        'unit_price',
        'cost',
        'tax',
        'net_amount',
        'qty',
        'discount_amount',
        'discount_type',
        'description'
    ];
}
