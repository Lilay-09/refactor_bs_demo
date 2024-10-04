<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = 'users';
    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'user_name',
        'name_km',
        'photo_file_name',
        'email',
        'gender',
        'bio',
        'latitude',
        'longtitude',
        'address',
        'dob',
        'otp',
        'otp_expiration',
        'last_login',
        'passowrd',
        'national_id',
        'system_admin',
        'delete_account',
        'plate_number',
        'employee_type',
        'shift',
        'vehicle_type',
        'driver_warehouse_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];

    public function user_roles(){
        return $this->hasMany(UserRoles::class,'user_id','id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (is_null($model->start_date)) {
                $model->start_date = $model->created_at;
            }
        });
    }
}
