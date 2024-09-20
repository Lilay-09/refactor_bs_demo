<?php

namespace App\Models;

use Carbon\Carbon;
use Config;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;
    protected $table = 'stock_adjustments';

    protected $fillable = [
        'ref_code',
        'reason',
        'type',
        'void',
        'void_uid',
        'status',
        'approved_uid',
        'approved_date',
        'warehouse_id',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function warehouse(){
        return $this->belongsTo(StockLocation::class,'warehouse_id','id');
    }

    public function createUser(){
        return $this->belongsTo(User::class,'create_uid','id');
    }
    public function updateUser(){
        return $this->belongsTo(User::class,'update_uid','id');
    }
    public function approveUser(){
        return $this->belongsTo(User::class,'approved_uid','id');
    }

    public function details(){
        return $this->hasMany(StockAdjustmentDetail::class,'stock_adjustment_id','id')->where('void',0);
    }
}
