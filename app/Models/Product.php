<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $table = 'products';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'model_id',
        'category_id',
        'country_id',
        'group_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'cost',
        'retail_price',
        'wholesale_price',
        'company_id'
    ];
    protected $hidden = [
        'wholesale_price',
    ];

    public function variants(){
        return $this->hasMany(ProductVariant::class,'product_id','id');
    }
    public function specifications(){
        return $this->hasMany(ProductVariantSpecification::class,'product_id','id');
    }

    public function tags(){
        return $this->hasMany(ProductVariantTag::class,'product_id','id');
    }

    public function category(){
        return $this->belongsTo(Category::class,'category_id','id');
    }
}
