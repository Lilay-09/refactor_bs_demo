<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    use HasFactory;
    protected $table = 'stock_locations';
    protected $fillable = [
        'id',
        'name',
        'description',
        'address',
        'main',
        'address_kh',
        'type_id',
        'create_uid',
        'update_uid',
        'company_id',
        'use_branch_id',
        'inactive',
        'branch_id'
    ];

    // Accessor to return 1 or 0
    public function getMainAttribute($value)
    {
        return $value ? 1 : 0;
    }

    public function getInactiveAttribute($value){
        return $value ? 1 : 0;
    }


    public function type(){
        return $this->belongsTo(StockLocationType::class, 'type_id', 'id');
    }
}
