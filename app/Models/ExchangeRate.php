<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;
    protected $table = 'exchange_rate';
    protected $fillable = [
        'x_date',
        'buy_rate',
        'sell_rate',
        'create_uid',
        'void',
        'void_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'currency_pair'
    ];

    protected $casts = [
        'x_date' => 'date:d-M-Y',
    ];
}
