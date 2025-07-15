<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderImage extends Model
{
    use HasFactory;
    protected $table = 'order_images';
    protected $fillable = [
        'id',
        'photo_file_name',
        'original_name',
        'order_id',
        'package_id',
        'user_type', // Added user_type to track who uploaded the image
        'size',
        'create_uid',
        'update_uid',
        'deleted_uid',
        'deleted_datetime',
        'is_deleted',
        'company_id',
        'branch_id',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id', 'id');
    }
}
