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
        'order_id'
    ];
}
