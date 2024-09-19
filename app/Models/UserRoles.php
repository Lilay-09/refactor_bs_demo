<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRoles extends Model
{
    use HasFactory;
    protected  $table = 'user_roles';
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'role_id'
    ];

    public function role(){
        return $this->belongsTo(Role::class,'role_id','id');
    }
}
