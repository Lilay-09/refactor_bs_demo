<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    use HasFactory;
    protected $table = 'product_groups';
    protected $fillable = [
        'id','name','name_kh','create_uid','update_uid','company_id','branch_id','void','void_uid'
    ];
}
