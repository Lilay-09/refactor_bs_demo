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
        'company_id'
    ];

    public function country(){
        return $this->belongsTo(Country::class);
    }
    public function districts(){
        return $this->hasMany(District::class,'city_id','id');
    }
}
