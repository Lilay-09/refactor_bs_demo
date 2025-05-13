<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentPosition extends Model
{
    use HasFactory;

    protected $table = 'employment_positions';
    protected $fillable = [
        'name_en',
        'department_id',
        'name_km',
        'description_en',
        'description_km',
        'create_uid',
        'update_uid',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'company_id',
        'branch_id'
    ];
}
