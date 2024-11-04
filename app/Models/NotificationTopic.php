<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTopic extends Model
{
    use HasFactory;
    protected $table = 'notification_topics';
    protected $fillable = [
        'token_id',
        'topic',
        'type',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_delete',
        'deleted_uid',
        'deleted_datetime',
    ];
}
