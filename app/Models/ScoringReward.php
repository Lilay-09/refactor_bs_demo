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
        'is_publish',
        'published_datetime',
        'channel',
        'message',
        'start_date',
        'expiration_date',
        'image',
        'reward_type',
        'amount',
        'currency_code',
        'claim_type_id',
        'unit',
        'unit_amount',
        'list',
        'max_usage',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];
}
