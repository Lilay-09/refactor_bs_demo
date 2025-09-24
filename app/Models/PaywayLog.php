<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaywayLog extends Model
{
    // use HasFactory;
    protected $table = 'payway_logs';
    protected $casts = [
        'details' => 'array', // automatically converts jsonb to array
        'payload' => 'array'
    ];
    protected $fillable = [
        'user_id',
        'user_type',
        'status',
        'type',
        'payload',
        'provider',
        'generated_at',
        'notes',
        'tran_id',
        'details'
        // 'create_uid',
        // 'update_uid',
        // 'is_deleted',
        // 'deleted_datetime',
        // 'deleted_reason'
    ];
}
