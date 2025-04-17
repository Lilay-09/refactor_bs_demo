<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $table = 'zones';
    protected $fillable = [
        'id',
        'zone_name',
        'zone_type',
        'parent_id',
        'identity',
        'zone_code',
        'commune',
        'district',
        'city',
        'country_id',
        'description',
        'status',
        'pin_map',
        'address',
        'lat_lng',
        'loc_lat',
        'radius_m',
        'radius_type',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];

    public function country(){
        return $this->belongsTo(Country::class,'country_id','id');
    }

    public function priceListZone(){
        return $this->hasMany(PriceListZone::class,'zone_id','id');
    }

    public function sub_zones(){
        return $this->hasMany(self::class,'parent_id','id');
    }
}
