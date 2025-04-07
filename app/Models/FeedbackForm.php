<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackForm extends Model
{
    // use HasFactory;
    protected $table = 'feedback_forms';
    protected $fillable = [
        'name_en',
        'name_km',
        'description',
        'channel',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'branch_id',
        'company_id'
    ];
}
