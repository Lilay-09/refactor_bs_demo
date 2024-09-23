<?php

namespace App\Models;

use Carbon\Carbon;
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
        'void',
        'void_uid',
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
        'updated_at' => 'date:d-M-Y',
        'created_at' => 'date:d-M-Y'
    ];

    public function getVendorType(){
        return $this->hasOne(VendorType::class, 'id', 'vendor_type_id');
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
