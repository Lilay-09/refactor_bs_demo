<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;
    protected $table = 'invoice_items';
    protected $fillable = [
        'id',
        'variant_id',
        'invoice_id',
        'qty',
        'unit_price',
        'net_amount',
        'tax',
        'cost',
        'discount_amount',
        'discount_type',
        'description'
    ];

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }
}
