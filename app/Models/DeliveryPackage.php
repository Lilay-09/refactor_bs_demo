<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryPackage extends Model
{
    use HasFactory;

    protected $table = 'delivery_packages';
    protected $fillable = [
        'id',
        'driver_notes',
        'notes',
        'status_id',
        'delivery_id',
        'drop_datetime',
        'package_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'branch_id',
        'company_id',
        'create_uid',
        'update_uid'
    ];
}
