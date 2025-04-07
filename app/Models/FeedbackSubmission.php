<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackSubmission extends Model
{
    // use HasFactory;
    protected $table = 'feedback_submissions';
    protected $fillable = [
        'form_id',
        'comment',
        'submitted_datetime',
        'user_id',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'branch_id',
        'company_id'
    ];
}
