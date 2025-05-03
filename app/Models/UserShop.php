<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserShop extends Model
{
    // use HasFactory;
    protected $table = 'user_shops';
    protected $fillable = [
        'owner_id',
        'name_en',
        'name_km',
        'address',
        'shop_type',
        'product_type_id',
        'pin_address',
        'loc_lat',
        'loc_lng',
        'phone',
        'email',
        'disclaimer',
        'country_id',
        'city',
        'district',
        'commune',
        'product_type',
        'est_pcs',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];

    public function product_type(){
        return $this->belongsTo(ProductType::class);
    }
}
