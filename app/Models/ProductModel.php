<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    use HasFactory;

    protected $table = 'models';

    protected $fillable = [
        'id','name','name_kh','brand_id','create_uid','update_uid','company_id','branch_id'
    ];

    public function brand(){
        return $this->belongsTo(Brand::class,'brand_id','id');
    }
}
