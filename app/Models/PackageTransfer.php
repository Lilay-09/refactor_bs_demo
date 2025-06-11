<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageTransfer extends Model
{
    // use HasFactory;
    protected $table = 'package_transfers';
    protected $fillable = [
        'transfer_datetime',
        'est_arrive_datetime',
        'transfer_datetime',
        'tranfer_qty',
        'tranfer_out_qty',
        'driver_id',
        'driver_name',
        'driver_phone',
        'transfer_uid',
        'location_type',
        'status_id',
        'from_location_id',
        'to_location_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];
}
