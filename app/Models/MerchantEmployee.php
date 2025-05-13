<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantEmployee extends Model
{
    use HasFactory;
    protected $table = 'merchant_employees';

    protected $fillable = [
        'name',
        'phone',
        'gender',
        'position',
        'employee_type',
        'phone',
        'address',
        'merchant_id',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
}
