<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoringReward extends Model
{
    use HasFactory;
    protected $table = 'scoring_rewards';
    protected $fillable = [
        'code',
        'mission',
        'description',
        'channel',
        'message',
        'start_date',
        'expiration_date',
        'image',
        'reward_type',
        'amount',
        'currency_code',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid'
    ];
}
