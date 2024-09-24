<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;
    protected $table = 'services';

    protected $fillable = [
        'id',
        'name',
        'name_kh',
        'description',
        'price',
        'branch_id',
        'company_id',
        'void',
        'void_uid',
        'create_uid',
        'update_uid',
        'photo_file_name'
    ];
}
