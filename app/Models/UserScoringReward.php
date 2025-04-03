<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserScoringReward extends Model
{
    use HasFactory;
    protected $table = 'user_scoring_rewards';
    protected $fillable = [
        'user_id',
        'target_package',
        'target_package_status_id',
        'reward_amount',
        'currency_code',
        'start_date',
        'reward_id'
    ];
}
