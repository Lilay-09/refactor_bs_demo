<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    use HasFactory;
    protected $table = 'product_types';

    protected $fillable = [
        'id','name','name_kh','row_order','update_uid','create_uid','company_id','branch_id',
        'is_deleted','deleted_uid','deleted_datetime'
    ];
}
