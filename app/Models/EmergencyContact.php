<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    use HasFactory;
    protected $table = 'emergency_contacts';

    protected $fillable = [
        'name_en',
        'name_km',
        'phone',
        'logo',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];
}
