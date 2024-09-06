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
        'type',
        'retail_price',
        'wholesale_price',
        'qty',
        'description',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
