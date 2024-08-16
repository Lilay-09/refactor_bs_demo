<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovementType extends Model
{
    use HasFactory;
    protected $table = 'stock_movement_types';

    protected $fillable = [
        'id',
        'name'
    ];
}
