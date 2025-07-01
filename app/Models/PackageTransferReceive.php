<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageTransferReceive extends Model
{
    // use HasFactory;

    protected $table = 'package_transfer_receives';
    protected $fillable = [
        'package_transfer_id',
        'location_id',
        'from_location_id',
        'receive_uid',
        'code',
        'remarks',
        'location_type',
        'from_location_type',
        'qty',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];

    public function receiveItems(){
        return $this->hasMany(PackageTransferReceiveItem::class,'package_transfer_receive_id');
    }

    public function transfer(){
        return $this->belongsTo(PackageTransfer::class,'package_transfer_id');
    }

    public function warehouse(){
        return $this->belongsTo(Warehouse::class,'location_id');
    }
    public function fromWarehouse(){
        return $this->belongsTo(Warehouse::class,'from_location_id');
    }
}
