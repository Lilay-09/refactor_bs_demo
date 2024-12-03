<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;
    protected $table = 'banners';
    protected $fillable = [
        'id',
        'photo_file_name',
        'description',
        'channel',
        'title',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];
}
