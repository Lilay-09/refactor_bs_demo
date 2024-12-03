<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedBack extends Model
{
    use HasFactory;
    protected $table = 'feedbacks';
    protected $fillable = [
        'id',
        'channel',
        'comments',
        'rate',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];
}
