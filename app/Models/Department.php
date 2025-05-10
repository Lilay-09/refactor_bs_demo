<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $table = 'departments';

    protected $fillable = [
        'name_en',
        'name_km',
        'description_en',
        'description_km',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'deleted_reason'
    ];
}
