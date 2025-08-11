<?php

namespace App\Models;

use DB;
use Helper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;
    protected $table = 'packages';
    // protected $casts = [
    //     'price' => 'float',
    //     'extra_charge' => 'float',
    //     'additional_fee' => 'float',
    //     'delivery_fee' => 'float',
    //     'taxi_fee' => 'float',
    //     'base_fee' => 'float',
    //     'driver_total' => 'float',
    // ];
    protected $fillable = [
        'main_zone_name',
        'main_zone_code',
        'receiver_lat',
        'receiver_lng',
        'qr_code',
        'driver_notes',
        'last_submit_uid',
        'driver_display_order',
        'last_remark_user',
        'package_name',
        'product_type',
        'returned_uid',
        'price',
        'price_khr',
        'dim_x',
        'taxi_fee',
        'dim_y',
        'dim_z',
        'remarks',
        'status_id',
        'prev_status_id',
        'current_status_id',
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
        'tracking_notes',
        'receiver_address',
        'zone_code',
        'zone_name',
        'receiver_phone',
        'returned_datetime',
        'receiver_name',
        'photo_id',
        'delivery_type',
        'additional_fee',
        'outstanding',
        'sender_id',
        'assign_uid',
        'actual_kg',
        'billed_kg',
        'delivered_date',
        'assigned_return_at',
        'assign_driver_datetime',
        'exchange_rate',
        'image_date',
        'merchant_total',
        'driver_total',
        'kick_notes',
        'update_uid',
        'delivery_remarks',
        'extra_charge',
        'kick_reason',
        'kick_uid',
        'driver_payment_id',
        'merchant_payment_id',
        'driver_disbursement_id',
        'merchant_disbursement_id',
        'driver_commission_id',
        'is_contact',
        'contact_reason',
        'priority_level',
        'arrive_warehouse_datetime',
        'warehouse_id',

        'cod_khr',
        'cod_usd',
        'cod_fee',
        'driver_cod_usd',
        'driver_cod_khr',
        'other_fee',

        'company_id',
        'branch_id',
        'create_uid',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',

        'location_type',

    ];

    public function getAssignDriverDatetimeAttribute($value)
    {
        return $this->formatDatetime($value);
    }

    public function receiverAddress(): Attribute
    {
        return Attribute::get(
            fn ($value) => $value ?? $this->zone_name
        );
    }


    // public function getArriveWarehouseDatetimeAttribute($value)
    // {
    //     return $this->formatDatetime($value);
    // }

    // public function getFailedDatetimeAttribute($value)
    // {
    //     return $this->formatDatetime($value);
    // }

    public function warehouse(){
        return $this->belongsTo(Warehouse::class,'warehouse_id');
    }

    public function activeDeliveryPackage()
{
    return $this->hasOne(DeliveryPackage::class,'package_id','id')
        ->latest('created_at');
}


    public function setDriverTotalAttribute($value)
    {
        $this->attributes['driver_total'] = Helper::getNumber($value);
    }

    public function setMerchantTotalAttribute($value)
    {
        $this->attributes['merchant_total'] = Helper::getNumber($value);
    }

    // public function getDeliveredDatetimeAttribute($value)
    // {
    //     return $this->formatDatetime($value);
    // }

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

    public function returnUser(){
        return $this->belongsTo(User::class,'returned_uid','id');
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

    // public function driverPayment(){
    //     return $this->belongsTo(PaymentPackage::class,'package_id')->where('payer_type','driver')->where('is_deleted',0);
    // }

    // public function merchantPayment(){
    //     return $this->belongsTo(PaymentPackage::class,'package_id')->where('payer_type','merchant')->where('is_deleted',0);
    // }

    public function payment()
    {
        return $this->belongsTo(PaymentPackage::class, 'package_id','package_id');
    }

    public function disbursement()
    {
        return $this->belongsTo(DisbursementPackage::class, 'package_id','package_id');
    }

    public function hasDriverPayment(): bool
    {
        return DB::table('payment_packages')
            ->where('package_id', $this->id)
            ->where('payer_type', 'driver')
            ->where('is_deleted', false)
            ->exists()
            ||
            DB::table('disbursement_packages')
            ->where('package_id', $this->id)
            ->where('payee_type', 'driver')
            ->where('is_deleted', false)
            ->exists();
    }

    // Check if merchant payment or disbursement exists for this package
    public function hasMerchantPayment(): bool
    {
        return DB::table('payment_packages')
            ->where('package_id', $this->id)
            ->where('payer_type', 'merchant')
            ->where('is_deleted', false)
            ->exists()
            ||
            DB::table('disbursement_packages')
            ->where('package_id', $this->id)
            ->where('payee_type', 'merchant')
            ->where('is_deleted', false)
            ->exists();
    }

    public function hasDriverCommissionPayment(): bool
    {
        return DB::table('disbursement_packages')
            ->where('package_id', $this->id)
            ->where('payee_type', 'driver')
            ->where('type', 'commission')
            ->where('is_deleted', false)
            ->exists();
    }

    // Optional: Combined check
    public function hasAnyPayment(): bool
    {
        return $this->hasDriverPayment() || $this->hasMerchantPayment();
    }


}
