<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackAnswer extends Model
{
    // use HasFactory;

    protected $table = 'feedback_answers';

    protected $fillable = [
        'submission_id',
        'question_id',
        'rating',
        'comment',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'branch_id',
        'company_id'
    ];
}
