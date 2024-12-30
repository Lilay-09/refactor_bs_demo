<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppModule extends Model
{
    use HasFactory;

    protected $table = 'app_modules';
    protected $fillable = [
        'id',
        'name',
        'code',
        'parent_id',
        'display_order',
        'name_kh',
        'native_name',
        'native_name_kh',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
    ];
}
