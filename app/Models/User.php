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
        'app_id',
        'code',
        'first_name',
        'last_name',
        'username',
        'pin_address',
        'has_commission',
        'name_km',
        'cod',
        'photo_file_name',
        'email',
        'gender',
        'phone',
        'bio',
        'login_name',
        'latitude',
        'longitude',
        'address',
        'dob',
        'otp',
        'otp_expiration',
        'last_login',
        'password',
        'national_id',
        'system_admin',
        'lock',
        'delete_account',
        'account_type',
        'plate_number',
        'shift_type',
        'start_time',
        'has_account',
        'end_time',
        'vehicle_type',
        'driver_warehouse_id',
        'employment_date',
        'relative_name',
        'relative_phone',
        'relative_relationship',
        'relative_address',
        'salary',
        'salary_date',
        'referrer_uid',
        'register_channel',
        'register_status',
        'registered_datetime',
        'employee_type',
        'is_available',
        'business_type',
        'client_type_id',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime'
    ];

    public function bank_accounts(){
        return $this->hasMany(UserBank::class,'user_id','id');
    }

    public function primaryBank(){
        return $this->hasOne(UserBank::class,'user_id','id')->where('is_primary',true);
    }

    public function merchantPriceList(){
        return $this->hasOne(MerchantPriceList::class,'merchant_id','id');
    }

    public function merchantPackages(){
        return $this->hasMany(Package::class,'merchant_id','id');
    }

    public function user_roles(){
        return $this->hasMany(UserRoles::class,'user_id','id');
    }

    public function shops(){
        return $this->hasMany(UserShop::class,'owner_id','id');
    }

    public function shop(){
        return $this->hasOne(UserShop::class,'owner_id');
    }
    public function merchantType(){
        return $this->belongsTo(ClientType::class,'client_type_id','id');
    }

    public function createUser(){
        return $this->belongsTo(User::class,'create_uid','id');
    }

    public function getDobAttribute($value)
    {
        return $this->formatDatetime($value,'d-M-Y');
    }

    protected function formatDatetime($value,$format='d-M-y h:i:s A')
    {
        if (!$value) {
            return null; // Handle null or empty values
        }

        // Parse and format the datetime, specifying the desired time zone
        return \Carbon\Carbon::parse($value)
            ->timezone(config('app.timezone')) // Convert to app time zone
            ->format($format);
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
        'salary_date' => 'datetime',
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
        // static::creating(function ($model) {
        //     if (is_null($model->start_date)) {
        //         $model->start_date = $model->created_at;
        //     }
        // });
    }
}
