<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentDescriptions extends Model
{
    // use HasFactory;
    protected $table = 'comment_descriptions';
    protected $fillable = [
        'id',
        'parent_id',
        'tmp_id',
        'thread_id',
        'comment_id',
        'data',
        'data_type',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
    ];

    public function replyTo()
    {
        return $this->belongsTo(CommentDescriptions::class, 'parent_id', 'id');
    }

}
