<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    use HasFactory;
    protected $table = 'user_permissions';
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;
    protected $fillable = [
        'role_id',
        'user_id',
        'permission_id',
    ];
}
