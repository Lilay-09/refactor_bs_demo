<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $table = 'product_variants';
    protected $fillable = [
        'id',
        'product_id',
        'size',
        'color',
        'sku',
        'material',
        'weight',
        'width',
        'length',
        'expires_at',
        'condition',
        'cost',
        'retail_price',
        'wholesale_price',
        'condition_percentage',
        'company_id',
        'branch_id',
        'create_uid',
        'update_uid'
    ];

    public function product(){
        return $this->belongsTo(Product::class,'product_id','id');
    }
}
