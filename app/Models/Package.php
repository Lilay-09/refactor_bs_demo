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
        'dim_y',
        'dim_z',
        'status_id',
        'failure_notes',
        'order_id',
        'payer',
        'cod',
        'delivery_fee',
        'receiver_address',
        'zone_code',
        'zone_name',
        'receiver_phone',
        'receiver_name',
        'delivery_type',
        'sender_id',
        'actual_kg',
        'billed_kg',
        'delivery_date',
        'arrival_date',
        'exchange_rate',
        'driver_id'
    ];
}
