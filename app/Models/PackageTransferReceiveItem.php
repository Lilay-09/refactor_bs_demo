<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageTransferReceiveItem extends Model
{
    // use HasFactory;
    protected $table = 'package_transfer_details';
    protected $fillable = [
        'pacakge_transfer_id',
        'package_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
    ];
}
