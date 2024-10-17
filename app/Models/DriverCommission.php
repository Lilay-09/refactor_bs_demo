<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverCommission extends Model
{
    use HasFactory;
    protected $table = 'driver_commissions';
    protected $fillable = [
        'driver_id',
        'delivery_type',
        'pickup_commission',
        'delivery_commission',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
}
