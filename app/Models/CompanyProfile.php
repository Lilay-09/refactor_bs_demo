<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    use HasFactory;
    protected $table = 'companies';

    protected $fillable = [
        'name',
        'name_km',
        'address',
        'email',
        'company_type',
        'phone',
        'description',
        'photo_file_name',
        'address_kh',
        'remarks',
        'cp_phone',
        'cp_email',
        'cp_name',
        'inactive',
        'create_uid',
        'update_uid'
    ];
}
