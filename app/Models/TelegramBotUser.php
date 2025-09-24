<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramBotUser extends Model
{
    // use HasFactory;
    protected $table = 'telegram_bot_users';
    protected $fillable = [
        'id',
        'group_name',
        'group_id',
        'default_caption',
        'company_id',
        'branch_id',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
}
