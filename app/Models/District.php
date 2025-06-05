<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $table = 'districts';
    protected $fillable = [
        'name_en',
        'id',
        'name_km',
        'city_id',
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
    public function city(){
        return $this->belongsTo(City::class);
    }
}
