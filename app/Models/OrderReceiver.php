<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderReceiver extends Model
{
    use HasFactory;
    protected $table = 'order_receivers';

    protected $fillable = [
        'id',
        'order_id',
        'receiver_phone',
        'receiver_name',
        'zone_code',
        'receiver_address',
        'dim_x',
        'dim_y',
        'dim_z',
        'weight_kg',
        'cod',
        'price',
        'payer',
        'sender_id',
        'actual_kg',
        'zone_name',
        'qr_code',
        'status_id',
        'delivery_notes',
        'base_fee'
    ];
}
