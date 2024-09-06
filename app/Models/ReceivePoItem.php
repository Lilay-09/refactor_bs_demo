<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivePoItem extends Model
{
    use HasFactory;
    protected $table = 'receive_po_items';
    protected $fillable = [
        'id',
        'received_qty',
        'batch_number',
        'receive_po_id',
        'purchase_order_item_id'
    ];
}
