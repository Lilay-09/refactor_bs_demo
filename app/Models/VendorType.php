<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorType extends Model
{
    use HasFactory;
    protected $table = 'vendor_types';
    protected $fillable = [
        'id',
        'name',
        'name_kh',
        'create_uid',
        'update_uid',
        'branch_id',
        'void',
        'void_uid',
        'company_id'
    ];

    protected $casts = [
        'updated_at' => 'date:d-M-Y H:i:s',
        'created_at' => 'date:d-M-Y H:i:s'
    ];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
