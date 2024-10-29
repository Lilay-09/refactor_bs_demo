<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'id',
        'code',
        'merchant_id',
        'vehicle_type',
        'delivery_type',
        'qty',
        'pickup_address',
        'pickup_location',
        'code',
        'status_id',
        'driver_id',
        'pickup_notes',
        'pickup_method',
        'request_pickup_datatime',
        'is_completed',
        'loc_lat',
        'loc_lng',
        'expiry_date',
        'actual_pkg_count',
        'detail_type',
        'warehouse_id',
        'order_datetime',
        'booking_channel',
        'create_uid',
        'update_uid',
        'branch_id',
        'user_class',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];

    public function getOrderDatetimeAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('M-d-Y H:i:s A');
    }

    public function packages(){
        return $this->hasMany(Package::class,'order_id','id');
    }

    public function merchant(){
        return $this->belongsTo(User::class,'merchant_id','id')->where('account_type','merchant')->where('is_deleted',0);
    }

    public function driver(){
        return $this->belongsTo(User::class,'driver_id','id')->where('is_deleted',0);
    }

    public function tracking_status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id')->where('is_deleted',0);
    }
}
