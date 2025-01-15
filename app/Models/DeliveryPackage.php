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
        'assign_uid',
        'delivery_id',
        'driver_id',
        'is_completed',
        'delay_count',
        'kick_reason',
        'kick_uid',
        'kick_notes',
        'failed_datetime',
        'delivered_datetime',
        'assign_driver_datetime',
        'tracking_notes',
        'delivery_remarks',
        'package_id',
        'dim_z',
        'has_swap',
        'dim_x',
        'dim_y',
        'is_contact',
        'contact_reason',
        'priority_level',
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

    public function package(){
        return $this->belongsTo(Package::class,'package_id','id')->where('is_deleted',0);
    }
}
