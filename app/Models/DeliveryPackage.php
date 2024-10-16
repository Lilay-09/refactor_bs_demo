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
        'driver_id',
        'is_completed',
        'delay_count',
        'failed_datetime',
        'delivered_datetime',
        'assign_driver_datetime',
        'package_id',
        'dim_z',
        'dim_x',
        'dim_y',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'branch_id',
        'company_id',
        'create_uid',
        'update_uid'
    ];

    public function status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id');
    }
}
