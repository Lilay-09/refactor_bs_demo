<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';
    protected $fillable = [
        'id',
        'name_en',
        'address',
        'cp_phone',
        'cp_name',
        'loc_lat',
        'loc_lng',
        'warehouse_type',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];
}
