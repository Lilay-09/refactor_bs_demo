<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentHistory extends Model
{
    use HasFactory;

    protected $table = 'employment_histories';
    protected $fillable = [
        'user_id',
        'previous_employment_type',
        'new_employment_type',
        'previous_rate',
        'new_rate',
        'currency',
        'previous_status',
        'new_status',
        'previous_position',
        'new_position',
        'previous_department',
        'new_department',
        'effective_date',
        'changed_by',
        'note',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'previous_rate' => 'decimal:2',
        'new_rate' => 'decimal:2',
    ];
}
