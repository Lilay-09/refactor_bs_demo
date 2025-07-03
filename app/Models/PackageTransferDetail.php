<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageTransferDetail extends Model
{
    // use HasFactory;
    protected $table = 'package_transfer_details';

    protected $fillable = [
        'id',
        'package_transfer_id',
        'status_id',
        'package_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];

    public function packageInfo(){
        return $this->belongsTo(Package::class,'package_id');
    }
}
