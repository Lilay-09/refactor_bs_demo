<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackQuestion extends Model
{
    // use HasFactory;
    protected $table = 'feedback_questions';
    protected $fillable = [
        'question_en',
        'question_km',
        'form_id',
        'display_order',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'branch_id',
        'company_id'
    ];
}
