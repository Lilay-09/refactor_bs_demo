<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBank extends Model
{
    use HasFactory;

    protected $table = 'user_bank_accounts';
    protected $fillable = [
        'id',
        'user_id',
        'bank_name',
        'bank_id',
        'currency',
        'bank_number',
        'account_name',
        'qr_image',
        'is_primary',
        'is_deleted',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
        'is_deleted',
        'delete_uid',
        'deleted_datetime'
    ];
}
