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
        'city',
        'postal_code',
        'country',
        'address_kh',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];

    protected $casts = [
        'updated_at' => 'date:d-M-Y'
    ];

    public function getVendorType(){
        return $this->hasOne(VendorType::class, 'id', 'vendor_type_id');
    }
}
