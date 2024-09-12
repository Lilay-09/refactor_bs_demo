<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;
    protected $table = 'banks';

    protected $fillable = [
        'name',
        'name_kh',
        'photo_file_name',
        'photo_file_name_kh',
        'inactive',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];

     public function getInactiveAttribute($value){
        return $value ? 1 : 0;
    }


}
