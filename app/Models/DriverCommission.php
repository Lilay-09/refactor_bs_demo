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
        'pickup_commission_type',
        'delivery_commission',
        'delivery_commission_type',
        'pickup_commission_start_date',
        'delivery_commission_start_date',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
}



