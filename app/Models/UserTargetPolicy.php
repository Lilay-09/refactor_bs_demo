<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTargetPolicy extends Model
{
    use HasFactory;
    protected $table = 'user_target_policies';

    protected $fillable = [
        'user_id',
        'target_value',
        'target_type',
        'monthly_bonus',
        'monthly_bonus_type',
        'yearly_bonus',
        'yearly_bonus_type',
        'effective_date',
        'period_type',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'branch_id',
        'company_id',
        'create_uid',
        'update_uid'
    ];
}
