<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserZone extends Model
{
    // use HasFactory;
    protected $table = 'user_zones';
    protected $fillable = [
        'id',
        'company_id',
        'branch_id',
        'zone_id',
        'assign_at',
        'user_id',
        'create_uid',
        'update_uid',
        'is_deleted',
        'delete_uid',
        'deleted_datetime'
    ];

    public function zone(){
        return $this->belongsTo(Zone::class, 'zone_id', 'id')->where('is_deleted',0);
    }

    public function sub_zones(){
        return $this->hasMany(UserSubZone::class, 'user_zone_id', 'id')->where('is_deleted',0);
    }
}

