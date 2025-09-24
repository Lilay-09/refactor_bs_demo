<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    // use HasFactory;
    protected $table = 'telegram_bots';
    protected $fillable =[
        'name',
        'token',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'company_id',
        'branch_id'
    ];
}
