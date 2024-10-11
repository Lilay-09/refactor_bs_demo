<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;
    protected $table = 'deliveries';
    protected $fillable = [
        'id',
        'fleet_tracking_number',
        'driver_id',
        'status_id',
        'depart_datetime',
        'remarks',
        'package_count',
        'delivered_count',
        'failed_count',
        'is_completed',
        'warehouse_id',
        'vehicle_type',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];


    public $cast = [
        'depart_datetime' => 'DateTime:d-M-y H:i:s'
    ];
    public function getDepartDatetimeAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('d-M-y H:i:s');
    }

    public function status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id');
    }
}
