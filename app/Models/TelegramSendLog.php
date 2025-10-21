<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use function PHPSTORM_META\map;

class TelegramSendLog extends Model
{
    // use HasFactory;
    protected $table = 'telegram_send_logs';
    protected $fillable = [
        'id',
        'sender_id',
        'receiver_id',
        'package_count',
        'unique',
        'sent_at',
        'start',
        'end'
    ];
}
