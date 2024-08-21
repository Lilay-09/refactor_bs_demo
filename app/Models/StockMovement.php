<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;
    protected $table = 'stock_movements';
    protected $fillable = [
        'id',
        'variant_id',
        'reference_no',
        'from_location_id',
        'to_location_id',
        'cost',
        'adjustment_qty',
        'transfer_in_qty',
        'transfer_out_qty',
        'sold_qty',
        'receive_qty',
        'description',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
