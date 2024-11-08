<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientType extends Model
{
    use HasFactory;
    protected $table = 'client_types';
    protected $fillable = [
        'id',
        'name',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
}
