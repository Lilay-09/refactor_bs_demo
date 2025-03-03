<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultAddress extends Model
{
    // use HasFactory;
    protected $table = 'default_addresses';

    protected $fillable = [
        'name',
        'hidden',
        'company_id',
        'branch_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'create_uid',
        'update_uid',
    ];
}
