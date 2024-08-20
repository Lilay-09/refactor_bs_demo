<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    protected $table = 'customers';
    protected $fillable = [
        'id',
        'name',
        'name_kh',
        'discount_percent',
        'email',
        'phone',
        'address',
        'description',
        'customer_type_id',
        'address_kh',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
