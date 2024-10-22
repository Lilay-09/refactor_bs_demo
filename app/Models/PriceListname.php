<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceListname extends Model
{
    use HasFactory;
    protected $table = 'price_list_names';

    protected $fillable = [
        'id',
        'name',
        'kg_mark',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];

}
