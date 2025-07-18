<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentUser extends Model
{
    // use HasFactory;
    protected $table = 'comment_users';
    protected $fillable = [
        'comment_id',
        'user_id',
        'user_type',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'company_id',
        'branch_id',
        'create_uid',
        'update_uid',
    ];

}
