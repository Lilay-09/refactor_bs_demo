<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasFactory;

    protected $table = 'vehicle_types';
    protected $fillable = [
        'id',
        'name',
        'name_km',
        'description_en',
        'description_km',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'is_deleted',
        'delete_uid',
        'deleted_datetime'
    ];
}
