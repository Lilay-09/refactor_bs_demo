<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantTag extends Model
{
    use HasFactory;
    protected $table = 'product_variant_tags';
    protected $fillable = [
        'id',
        'tag',
        'variant_id',
        'product_id',
        'branch_id',
        'company_id',
        'create_uid',
        'update_uid'
    ];
}
