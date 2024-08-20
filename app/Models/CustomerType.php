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
        'branch_id',
        'company_id'
    ];
}
