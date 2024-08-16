<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;
    protected $table = 'stocks';
    protected $fillable = [
        'sku',
        'batch_number',
        'stock_location_id',
        'variant_id',
        'qty',
        'cost',
        'wholesale_price',
        'retail_price',
        'expiration_date',
        'status',
        'created_at',
        'updated_at',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
    ];
}
