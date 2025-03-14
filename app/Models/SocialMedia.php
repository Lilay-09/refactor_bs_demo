<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialMedia extends Model
{
    use HasFactory;
    protected $table = 'social_medias';
    protected $fillable = [
        'id',
        'url',
        'name',
        'account_name',
        'photo_file_name',
        'create_uid',
        'update_uid',
        'delete_uid',
        'deleted_datetime',
        'is_deleted',
        'company_id',
        'branch_id'
    ];
}
