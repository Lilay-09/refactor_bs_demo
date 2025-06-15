<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageTransfer extends Model
{
    // use HasFactory;
    protected $table = 'package_transfers';
    protected $fillable = [
        'transfer_datetime',
        'est_arrive_datetime',
        'transfer_datetime',
        'transfer_qty',
        'transfer_out_qty',
        'driver_id',
        'driver_name',
        'driver_phone',
        'transfer_uid',
        'location_type',
        'status_id',
        'from_location_id',
        'to_location_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];

    public function getRemainingQtyAttribute(): int{
        return $this->transfer_qty - $this->transfer_out_qty;
    }

    public function getStatusAttribute(): string{
        return TransferStatus::tryFrom($this->status_id)->label();
    }


    public function fromWarehouse(){
        return $this->belongsTo(Warehouse::class,'from_location_id','id');
    }
    public function toWarehouse(){
        return $this->belongsTo(Warehouse::class,'to_location_id','id');
    }

    public function transfer_items(){
        return $this->hasMany(PackageTransferDetail::class,'package_transfer_id','id');
    }
}
