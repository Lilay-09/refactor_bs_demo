<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;
    protected $table = 'stock_movements';
    protected $fillable = [
        'id',
        'variant_id',
        'item_ref',
        'reference_no',
        'from_location_id',
        'to_location_id',
        'cost',
        'type',
        'missing_qty',
        'retail_price',
        'wholesale_price',
        'transfer_uid',
        'qty',
        'status',
        'approved_uid',
        'approved_date',
        'description',
        'create_uid',
        'update_uid',
        'branch_id',
        'created_at',
        'updated_at',
        'company_id'
    ];

    public function transOutWarehouse(){
        return $this->belongsTo(StockLocation::class,'from_location_id','id');
    }

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function transInWarehouse(){
        return $this->belongsTo(StockLocation::class,'to_location_id','id');
    }



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
