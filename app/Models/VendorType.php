<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorType extends Model
{
    use HasFactory;
    protected $table = 'vendor_types';
    protected $fillable = [
        'id',
        'name',
        'name_kh',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
