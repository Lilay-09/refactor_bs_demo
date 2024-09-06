<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_items';
    protected $fillable = [
        'id',
        'purchase_id',
        'variant_id',
        'product_id',
        'qty',
        'unit_price',
        'due_amount',
        'total_price',
        'discount_amount',
        'discount_type',
        'received_qty',
        'branch_id',
        'company_id',
        'create_uid',
        'update_uid'
    ];

    public function variant(){
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id')->with('productInfo:id,name,code,description');
    }

    public function productWithVariant(){
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

}
