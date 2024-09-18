<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyStock extends Model
{

    ///** Final Stock = (begin_qty + receive_qty + transfer_in_qty) - (sold_qty + transfer_out_qty + missing_qty) */
    use HasFactory;
    protected $table = 'daily_stocks';

    protected $fillable = [
        'id',
        'stock_location_id',
        'variant_id',
        'begin_qty',
        'ending_qty',
        'adjustment_qty',
        'transfer_in_qty',
        'transfer_out_qty',
        'sold_qty',
        'receive_qty',
        'missing_qty',
        'company_id',
        'branch_id',
        'create_uid',
        'update_uid'
    ];
}
