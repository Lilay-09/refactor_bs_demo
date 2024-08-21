<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptItem extends Model
{
    use HasFactory;
    protected $table = 'receipt_items';
    protected $fillable = [
        'id',
        'variant_id',
        'receipt_id',
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
