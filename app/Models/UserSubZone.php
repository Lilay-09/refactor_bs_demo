<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubZone extends Model
{
    // use HasFactory;
    protected $table = 'user_sub_zones';
    protected $fillable = [
        'company_id',
        'branch_id',
        'user_zone_id',
        'assign_at',
        'zone_id',
        'create_uid',
        'update_uid',
        'is_deleted',
        'delete_uid',
        'deleted_datetime'
    ];

    public function zone(){
        return $this->belongsTo(Zone::class, 'zone_id', 'id');
    }
}
