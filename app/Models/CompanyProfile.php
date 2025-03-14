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
        'disclaimer',
        'email',
        'company_type',
        'phone',
        'description',
        'photo_file_name',
        'website',
        'address_kh',
        'cp_name',
        'cp_phone',
        'cp_email',
        'cp_name',
        'inactive',
        'create_uid',
        'update_uid'
    ];
}
