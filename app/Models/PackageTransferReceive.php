<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageTransferReceive extends Model
{
    // use HasFactory;

    protected $table = 'package_transfer_receives';
    protected $fillable = [
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
}
