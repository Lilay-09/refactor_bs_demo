<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovementType extends Model
{
    use HasFactory;

    protected $table = 'movement_types';
    public $timestamps = false;
    protected $fillable = [
        'name',
        'void',
        'void_uid'
    ];
}
