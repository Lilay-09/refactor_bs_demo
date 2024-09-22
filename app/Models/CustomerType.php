<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    use HasFactory;
    protected $table = 'customer_types';
    protected $fillable = [
        'id',
        'name',
        'name_kh',
        'discount_percent',
        'create_uid',
        'update_uid',
        'void',
        'void_uid',
        'branch_id',
        'company_id'
    ];

    protected $casts = [
        'updated_at' => 'date:d-M-Y',
    ];
}
