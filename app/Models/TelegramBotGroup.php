<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramBotGroup extends Model
{
    // use HasFactory;
    protected $table = 'telegram_bot_groups';
    protected $fillable = [
        'id',
        'bot_id',
        'group_name',
        'group_id'
    ];
}
