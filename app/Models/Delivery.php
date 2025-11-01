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
        'tracking_notes',
        'package_count',
        'delivered_count',
        'failed_count',
        'finished',
        'finised_uid',
        'finished_datetime',
        'finished_reason',
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
        return \Carbon\Carbon::parse($value)->format('d-M-y h:i:s A');
    }

    public function driver(){
        return $this->belongsTo(User::class,'driver_id','id');
    }

    public function status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id');
    }

    public function packages(){
        return $this->hasMany(DeliveryPackage::class,'delivery_id','id')
        ->where('is_deleted',0)
        ->orderByDesc('id');
    }

    /**
     * Get unique packages per delivery (for phone search)
     */
    public function distinctPackages()
    {
        return $this->hasMany(DeliveryPackage::class, 'delivery_id', 'id')
                    ->where('is_deleted', 0)
                    ->select('id', 'delivery_id', 'package_id') // only necessary fields
                    ->groupBy('package_id'); // ensures unique package_id
    }


}
