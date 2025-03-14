<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantPriceList extends Model
{
    use HasFactory;

    protected $table = 'merchant_price_list';
    protected $fillable = [
        'id',
        'merchant_id',
        'price_list_id',
        'zone_id',
        'zone_code',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
}
