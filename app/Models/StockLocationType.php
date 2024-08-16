<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLocationType extends Model
{
    use HasFactory;
    protected $table = 'stock_location_types';

    protected $fillable = [
        'id',
        'name'
    ];

}
