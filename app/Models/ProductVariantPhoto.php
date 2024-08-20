<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantPhoto extends Model
{
    use HasFactory;
    protected $table = 'product_variant_photos';
    protected $fillable = [
        'id',
        'variant_id',
        'photo_file_name',
        'directory',
        'is_thumbnail'
    ];
}
