<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';
    protected $fillable = [
        'topic_id',
        'service_name',
        'title',
        'body',
        'message_data',
        'image_url',
        'photo_file_name',
        'status',
        'sent_datetime',
        'is_read',
        'read_datetime',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_delete',
        'deleted_uid',
        'deleted_datetime',
    ];
}
