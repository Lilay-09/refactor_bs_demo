<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantSpecification extends Model
{
    use HasFactory;
    protected $table = 'product_variant_specifications';
    protected $fillable = [
        'id','name','value','product_id','variant_id','create_uid','update_uid','company_id','branch_id'
    ];
}
