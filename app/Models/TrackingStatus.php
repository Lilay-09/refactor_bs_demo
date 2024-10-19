<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingStatus extends Model
{
    use HasFactory;
    protected $table = 'tracking_statuses';

    protected $fillable = [
        'id',
        'name',
        'hidden',
        'stage',
        'display_order',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'branch_id',
        'create_uid',
        'update_uid'
    ];
}
