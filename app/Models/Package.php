<?php

namespace App\Models;

use App\Enums\ImageDirectory;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use Helper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory,LogsActivity;
    protected $table = 'packages';
    public $skipLog = false;
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
        'id',
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
        'method',
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
        'pickup_uid',
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
        'original_driver_cod_khr',
        'original_driver_cod_usd',
        'currency',
        'other_fee',
        'company_id',
        'branch_id',
        'create_uid',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'location_type',

    ];


    public function orderImage()
    {
        return $this->hasOne(OrderImage::class, 'package_id')
            ->orderByRaw("
                CASE
                    WHEN user_type = 'admin' THEN 1
                    WHEN user_type = 'driver' THEN 2
                    WHEN user_type = 'merchant' THEN 3
                    ELSE 4
                END
            ")
            ->latest('id');
    }

    public function getImageUrlAttribute()
    {
        if (!$this->orderImage?->photo_file_name) {
            return null; // no image found
        }
        return Helper::getImageUrl($this->orderImage->photo_file_name,1,ImageDirectory::ORDER_IMAGE->value,Helper::dateYMD($this->orderImage->created_at));
    }

    public function submitImage(){
        return $this->hasOne(PackageAttachment::class,'package_id')->where('status','return');
    }

    

    public function getReturnImageUrlAttribute()
    {
        if (!$this->submitImage?->file_name) {
            return null; // no image found
        }

        return Helper::getImageUrl($this->submitImage->file_name,1,ImageDirectory::ORDER_IMAGE->value,Helper::dateYMD($this->submitImage->created_at));
    }

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
    

    public function branchLocation(){
        return $this->belongsTo(Branch::class,'branch_id');
    }

    public function driverPackages()
    {
        return $this->hasMany(Package::class, 'driver_id', 'driver_id');
    }

    public function getLastTransferDriver(){
        return $this->hasOne(DeliveryPackage::class,'package_id')->orderBy('id','desc');
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

    public function pickupDriver(){
        return $this->belongsTo(User::class,'pickup_uid','id');
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

    // public function driver_payment(){
    //     return $this->belongsTo(Payment::class,'driver_payment_id','id');
    // }

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
        return $this->paymentPackages()
            ->whereHas('payment', function ($q) {
                $q->where('is_deleted', false);
            })
            ->where('payer_type', 'driver')
            ->where('is_deleted', false)
            ->exists()
            ||
            $this->disbursementPackages()
            ->whereHas('disbursement', function ($q) {
                $q->where('is_deleted', false);
            })
            ->where('payee_type', 'driver')
            ->where('is_deleted', false)
            ->exists();
    }

    // Check if merchant payment or disbursement exists for this package
    public function hasMerchantPayment(): bool
    {
        return $this->paymentPackages()
            ->where('package_id', $this->id)
            ->whereHas('payment',function ($q){
                $q->where('is_deleted',false);
            })
            ->where('payer_type', 'merchant')
            ->where('is_deleted', false)
            ->exists()
            ||
            $this->disbursementPackages()
            ->whereHas('disbursement',function ($q){
                $q->where('is_deleted',false);
            })
            ->where('package_id', $this->id)
            ->where('payee_type', 'merchant')
            ->where('is_deleted', false)
            ->exists();
    }

    public function hasDriverCommissionPayment(): bool
    {
        return DB::table('disbursement_packages')
            ->whereHas('disbursements',function ($q){
                $q->where('is_deleted',false);
            })
            ->where('package_id', $this->id)
            ->where('payee_type', 'driver')
            ->where('type', 'commission')
            ->where('is_deleted', false)
            ->exists();
    }

    // public function scopeWithoutDriverPayment($query)
    // {
    //     return $query
    //         ->whereNotExists(function ($q) {
    //             $q->select(DB::raw(1))
    //                 ->from('payment_packages')
    //                 ->whereColumn('payment_packages.package_id', 'packages.id')
    //                 ->where('payer_type', 'driver')
    //                 ->where('is_deleted', false);
    //         })
    //         ->whereNotExists(function ($q) {
    //             $q->select(DB::raw(1))
    //                 ->from('disbursement_packages')
    //                 ->whereColumn('disbursement_packages.package_id', 'packages.id')
    //                 ->where('payee_type', 'driver')
    //                 ->where('is_deleted', false);
    //         });
    // }

    public function scopeWithoutUserPayment($query, string $alias = 'packages',$userType='driver')
    {
        return $query
            ->whereNotExists(function ($q) use ($alias,$userType) {
                $q->select(DB::raw(1))
                    ->from('payment_packages')
                    ->whereColumn('payment_packages.package_id', "{$alias}.id")
                    ->where('payer_type', $userType)
                    ->where('is_deleted', false);
            })
            ->whereNotExists(function ($q) use ($alias,$userType) {
                $q->select(DB::raw(1))
                    ->from('disbursement_packages')
                    ->whereColumn('disbursement_packages.package_id', "{$alias}.id")
                    ->where('payee_type', $userType)
                    ->where('is_deleted', false);
            });
    }

    public function scopeWithUserPayment($query, string $alias = 'packages', string $userType = 'driver')
    {
        return $query->whereExists(function ($q) use ($alias, $userType) {
            $q->select(DB::raw(1))
                ->from('payment_packages')
                ->whereColumn('payment_packages.package_id', "{$alias}.id")
                ->where('payer_type', $userType)
                ->where('is_deleted', false);
        })->whereExists(function ($q) use ($alias, $userType) {
            $q->select(DB::raw(1))
                ->from('disbursement_packages')
                ->whereColumn('disbursement_packages.package_id', "{$alias}.id")
                ->where('payee_type', $userType)
                ->where('is_deleted', false);
        
        });
    }

    public function scopeWithoutDriverPayment($query, string $alias = 'packages')
    {
        return $query
            ->whereNotExists(function ($q) use ($alias) {
                $q->select(DB::raw(1))
                    ->from('payment_packages')
                    ->whereColumn('payment_packages.package_id', "{$alias}.id")
                    ->where('payer_type', 'driver')
                    ->where('is_deleted', false);
            })
            ->whereNotExists(function ($q) use ($alias) {
                $q->select(DB::raw(1))
                    ->from('disbursement_packages')
                    ->whereColumn('disbursement_packages.package_id', "{$alias}.id")
                    ->where('payee_type', 'driver')
                    ->where('is_deleted', false);
            });
    }

    public function scopeWithoutMerchantPayment($query)
    {
        
        $from = $query->getQuery()->from; // e.g. "packages" or "packages as p"

        // If aliased, take just the alias part
        if (str_contains(strtolower($from), ' as ')) {
            $parts = preg_split('/\s+as\s+/i', $from);
            $table = $parts[1]; // alias (e.g. "p")
        } else {
            $table = $from; // default (e.g. "packages")
        }
        return $query
        ->whereExists(function ($q) use ($table) {
            $q->select(DB::raw(1))
                ->from('payment_packages')
                ->whereColumn('payment_packages.package_id', $table.'.id')
                ->where('payer_type', 'merchant')
                ->where('is_deleted', false);
        })
        ->orWhereExists(function ($q) use ($table) {
            $q->select(DB::raw(1))
                ->from('disbursement_packages')
                ->whereColumn('disbursement_packages.package_id', $table.'.id')
                ->where('payee_type', 'merchant')
                ->where('is_deleted', false);
        });
    }


    // Optional: Combined check
    public function hasAnyPayment(): bool
    {
        return $this->hasDriverPayment() || $this->hasMerchantPayment();
    }

    public function comment(){
        return $this->hasOne(Comment::class, 'thread_id', 'id');
    }


    public function paymentPackages()
    {
        return $this->hasMany(PaymentPackage::class, 'package_id','package_id')
                ->where('is_deleted', false)
                ->with(['payment' => function($q) {
                    $q->where('is_deleted', false);
                }]);
    }

    public function disbursementPackages()
    {
        return $this->hasMany(DisbursementPackage::class, 'package_id','package_id')
                ->where('is_deleted', false)
                ->with(['disbursement' => function($q) {
                    $q->where('is_deleted', false);
                }]);
    }


    public function paymentDetails()
    {
        return $this->hasManyThrough(
            PaymentDetail::class,
            PaymentPackage::class,
            'package_id',  // FK on PaymentPackage
            'payment_id',  // FK on PaymentDetail
            'id',          // Local key on Package
            'payment_id'   // Local key on PaymentPackage
        )->where('payment_packages.is_deleted', false); // ensure only active pivot
    }

    public function disbursementDetails()
    {
        return $this->hasManyThrough(
            DisbursementDetails::class,
            DisbursementPackage::class,
            'package_id',      // FK on DisbursementPackage
            'disbursement_id', // FK on DisbursementDetail
            'id',              // Local key on Package
            'disbursement_id'  // Local key on DisbursementPackage
        )->where('disbursement_packages.is_deleted', false); // ensure only active pivot
    }

    public function deletedUser(){
        return $this->belongsTo(User::class,'deleted_uid','id');
    }

    public function createUser(){
        return $this->belongsTo(User::class,'create_uid','id');
    }   

}
