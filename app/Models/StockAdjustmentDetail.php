<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentDetail extends Model
{
    use HasFactory;
    protected $table = 'stock_adjustment_details';
    protected $fillable = [
        'id',
        'stock_adjustment_id',
        'item_ref',
        'variant_id',
        'qty',
        'reason',
        'void',
        'cost',
        'retail_price',
        'void_uid',
        'status',
        'approved_uid',
        'approved_date',
        'warehouse_id',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
    ];

    public function createUser(){
        return $this->belongsTo(User::class,'create_uid','id');
    }
    public function updateUser(){
        return $this->belongsTo(User::class,'update_uid','id');
    }
    public function approveUser(){
        return $this->belongsTo(User::class,'approved_uid','id');
    }
}
