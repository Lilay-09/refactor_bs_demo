<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    use HasFactory;
    protected $table = 'price_list';

    protected $fillable = [
        'id',
        'price',
        'base_fee',
        'below_kg',
        'below_kg_price',
        'price_list_name_id',
        'above_kg',
        'above_kg_price',
        'delivery_type',
        'apply_all_zones',
        'status',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];

    public function zones(){
        return $this->hasManyThrough(Zone::class,PriceListZone::class,'price_list_id','id','id','zone_id');
    }

    public function priceListName(){
        return $this->belongsTo(PriceListname::class,'price_list_name_id','id');
    }

}
