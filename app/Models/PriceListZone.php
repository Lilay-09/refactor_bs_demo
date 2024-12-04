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
        'identifier'
    ];

    public function zone(){
        return $this->belongsTo(Zone::class,'zone_id','id');
    }

}
