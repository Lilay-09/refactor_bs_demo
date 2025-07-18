<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    // use HasFactory;
    protected $table = 'comments';
    protected $fillable = [
        'thread_id',
        'data',
        'source',
        'data_type',
        'started_at',
        'ended_at',
        'starter_id',
        'user_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'company_id',
        'branch_id',
        'create_uid',
        'update_uid',
    ];
    public function descriptions()
    {
        return $this->hasMany(CommentDescriptions::class, 'comment_id', 'id');
    }
}
