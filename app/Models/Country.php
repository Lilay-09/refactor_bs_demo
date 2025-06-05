<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name_en',
        'name_km',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
    public function cities(){
        return $this->hasMany(City::class,'country_id','id');
    }

    public function getUpdatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('d-M-y H:i:s');
    }
}
