<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultRemark extends Model
{
    use HasFactory;
    protected $table = 'default_remarks';
    protected $fillable = [
        'id',
        'channel',
        'remarks',
        'create_uid',
        'channel',
        'hidden',
        'category',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'delete_datetime'
    ];
}
