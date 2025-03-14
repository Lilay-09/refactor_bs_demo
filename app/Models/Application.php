<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;
    protected $table = 'applications';
    public $incrementing = false;  // As the ID is not auto-incrementing
    protected $primaryKey = 'id';
    protected $fillable = [
        'id',
        'name',
        'app_type',
        'is_mobile_app',
        'user_class',
        'is_deleted',
        'deleted_uid',
        'daleted_datetime',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
