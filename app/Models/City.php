<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;
    protected $table = 'cities';
    protected $fillable = [
        'name',
        'id',
        'name_kh',
        'country_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];

    public function getUpdatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('d-M-y H:i:s');
    }
    public function country(){
        return $this->belongsTo(Country::class);
    }
    public function districts(){
        return $this->hasMany(District::class,'city_id','id');
    }
}
