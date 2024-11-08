<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessType extends Model
{
    use HasFactory;
    protected $table = 'business_types';
    protected $fillable = [
        'id',
        'name',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'deleted_uid',
        'deleted_datetime',
        'is_deleted'
    ];
}
