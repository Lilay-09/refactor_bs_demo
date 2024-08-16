<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;
    protected $table = 'vendors';
    protected $fillable = [
        'id',
        'name',
        'name_kh',
        'email',
        'phone',
        'address',
        'vendor_type_id',
        'address_kh',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
