<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;
    protected $table = 'banks';
    protected $fillable = [
        'id',
        'name',
        'photo_file_name',
        'is_deleted',
        'deleted_uid',
        'daleted_datetime',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
