<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivacyStatement extends Model
{
    use HasFactory;
    protected $table = 'privacy_statements';
    protected $fillable = [
        'id',
        'text',
        'channel',
        'is_deleted',
        'deleted_datetime',
        'deleted_uid',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
