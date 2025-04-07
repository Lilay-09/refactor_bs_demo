<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockZone extends Model
{
    use HasFactory;
    protected $table = 'stock_zones';

    protected $fillable = [
        'name',
        'warehouse_id',
        'type',
        'description',
        'is_deleted',
        'create_uid',
        'update_uid',
        'deleted_uid',
        'deleted_datetime'
    ];
}
