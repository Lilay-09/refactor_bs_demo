<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;
    protected $table = 'packages';
    protected $fillable = [
        'id',
        'qr_code',
        'package_name',
        'product_type',
        'price',
        'dim_x',
        'dim_y',
        'dim_z',
        'status_id',
        'failure_notes',
        'order_id',
        'payer',
        'cod',
        'delivery_fee',
        'receiver_address',
        'zone_code',
        'zone_name',
        'receiver_phone',
        'receiver_name',
        'delivery_type',
        'additional_fee',
        'outstanding',
        'sender_id',
        'actual_kg',
        'billed_kg',
        'delivered_date',
        'assign_driver_datetime',
        'exchange_rate',
        'merchant_total',
        'driver_total',
        'driver_id',
        'company_id',
        'branch_id',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'arrive_warehouse_datetime'
    ];

    public function status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id');
    }
}
