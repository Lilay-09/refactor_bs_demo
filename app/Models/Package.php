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
        'tracking_notes',
        'receiver_address',
        'zone_code',
        'zone_name',
        'photo_file_name',
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
        'kick_notes',
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
        'arrive_warehouse_datetime'
    ];

    // protected $casts = [
    //     'cod' => 'boolean',  // Automatically casts 0/1 to true/false when accessing the attribute
    // ];
    // public function getCodAttribute($value)
    // {
    //     return $value ? 1:0; // Converts 1/0 to true/false
    // }


    // public function getAssignDriverDatetimeAttribute($value){
    //     return \Carbon\Carbon::parse($value)->format('d-M-y H:i:s A');
    // }

    // public function getArriveWarehouseDatetimeAttribute($value){
    //     return \Carbon\Carbon::parse($value)->format('d-M-y H:i:s A');
    // }

    // public function getFailedDatetimeAttribute($value){
    //     return \Carbon\Carbon::parse($value)->format('d-M-y H:i:s A');
    // }

    // public function getDeliveredDatetimeAttribute($value){
    //     return \Carbon\Carbon::parse($value)->format('d-M-y H:i:s A');
    // }
    public function getAssignDriverDatetimeAttribute($value)
    {
        return $this->formatDatetime($value);
    }

    public function getArriveWarehouseDatetimeAttribute($value)
    {
        return $this->formatDatetime($value);
    }

    public function getFailedDatetimeAttribute($value)
    {
        return $this->formatDatetime($value);
    }

    public function getDeliveredDatetimeAttribute($value)
    {
        return $this->formatDatetime($value);
    }

    protected function formatDatetime($value)
    {
        if (!$value) {
            return null; // Handle null or empty values
        }

        // Parse and format the datetime, specifying the desired time zone
        return \Carbon\Carbon::parse($value)
            ->timezone(config('app.timezone')) // Convert to app time zone
            ->format('d-M-y h:i:s A');
    }
    public function status(){
        return $this->belongsTo(TrackingStatus::class,'status_id','id');
    }

    public function driver(){
        return $this->belongsTo(User::class,'driver_id','id');
    }

    public function merchant(){
        return $this->belongsTo(User::class,'merchant_id','id');
    }
    public function updateUser(){
        return $this->belongsTo(User::class,'update_uid','id');
    }
    public function order(){
        return $this->belongsTo(Order::class,'order_id','id');
    }

    public function driver_payment(){
        return $this->belongsTo(Payment::class,'driver_payment_id','id');
    }
}
