<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandImage extends Model
{
    use HasFactory;
    protected $table = 'brand_images';

    protected $fillable = [
        'id',
        'channel',
        'photo_file_name',
        'hidden',
        'title',
        'description',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
