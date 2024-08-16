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
        'address_kh',
        'type_id',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];

    public function type(){
        return $this->belongsTo(StockLocationType::class, 'type_id', 'id');
    }
}
