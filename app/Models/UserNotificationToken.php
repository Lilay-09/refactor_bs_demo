<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotificationToken extends Model
{
    use HasFactory;
    protected $table = 'user_notification_tokens';

    protected $fillable = [
        'id',
        'user_id',
        'service_name',
        'token',
        'device_id',
        'os_name',
        'platform',
        'is_active',
        'last_notifed_at',
        'expires_at',
        'subscribe_datetime',
        'create_uid',
        'update_uid',
        'deleted_uid',
        'deleted_datetime',
        'is_deleted',
        'branch_id',
        'company_id'
    ];

    public function user(){
        return $this->belongsTo(User::class,'user_id','id');
    }
}
