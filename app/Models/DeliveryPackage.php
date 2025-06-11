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
        'package_id',
        'delivery_id',
        'main_zone_name',
        'main_zone_code',
        'receiver_lat',
        'receiver_lng',
        'qr_code',
        'last_submit_uid',
        'driver_display_order',
        'last_remark_user',
        'package_name',
        'product_type',
        'returned_uid',
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
        // 'return_uid',
        'payer',
        'cod',
        'driver_id',
        'delivery_fee',
        'receiver_address',
        'zone_code',
        'zone_name',
        'photo_file_name',
        'receiver_phone',
        'returned_datetime',
        'receiver_name',
        'delivery_type',
        'additional_fee',
        'outstanding',
        'sender_id',
        'assign_uid',
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
        'kick_notes',
        'notes',
        'update_uid',
        'delivery_remarks',
        'extra_charge',
        'kick_reason',
        'kick_uid',
        'is_deleted',
        'driver_payment_id',
        'merchant_payment_id',
        'driver_disbursement_id',
        'merchant_disbursement_id',
        'driver_commission_id',
        'is_contact',
        'contact_reason',
        'priority_level',
        'deleted_uid',
        'deleted_datetime',
        'arrive_warehouse_datetime',
        'warehouse_id',

        'cod_khr',
        'cod_usd',
        'driver_cod_usd',
        'driver_cod_khr',

        'is_completed',
        'delay_count',

        // 'driver_notes',
        // 'order_id',
        // 'notes',
        // 'status_id',
        // 'assign_uid',
        // 'delivery_id',
        // 'driver_id',
        // 'is_completed',
        // 'delay_count',
        // 'kick_reason',
        // 'kick_uid',
        // 'kick_notes',
        // 'failed_datetime',
        // 'delivered_datetime',
        // 'assign_driver_datetime',
        // 'tracking_notes',
        // 'delivery_remarks',
        // 'package_id',
        // 'dim_z',
        // 'has_swap',
        // 'dim_x',
        // 'dim_y',
        // 'is_contact',
        // 'contact_reason',
        // 'priority_level',
        // 'is_deleted',
        // 'deleted_datetime',
        // 'deleted_uid',
        // 'branch_id',
        // 'company_id',
        // 'create_uid',
        // 'update_uid'
    ];

    public function status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id');
    }

    public function package(){
        return $this->belongsTo(Package::class,'package_id','id')->where('is_deleted',0);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class,'delivery_id');
    }

}
