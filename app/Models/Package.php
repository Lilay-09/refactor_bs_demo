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
        'taxi_fee',
        'dim_y',
        'dim_z',
        'remarks',
        'status_id',
        'failure_notes',
        'merchant_id',
        'failed_datetime',
        'delivered_datetime',
        'pickup_notes',
        'pickup_datetime',
        'order_id',
        'payer',
        'cod',
        'driver_id',
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
        'company_id',
        'branch_id',
        'create_uid',
        'update_uid',
        'extra_charge',
        'is_deleted',
        'driver_payment_id',
        'merchant_payment_id',
        'is_contact',
        'contact_reason',
        'priority_level',
        'deleted_uid',
        'deleted_datetime',
        'arrive_warehouse_datetime'
    ];

    public function status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id');
    }
}
