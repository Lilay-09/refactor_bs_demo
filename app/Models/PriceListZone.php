<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceListZone extends Model
{
    use HasFactory;
    protected $table = 'price_list_zones';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'zone_id',
        'price_list_id',
        'identifier',
        'base_fee',
        'additional_fee',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];

    public function zone(){
        return $this->belongsTo(Zone::class,'zone_id','id');
    }

    public function priceList(){
        return $this->belongsTo(PriceList::class,'price_list_id','id');
    }

}
