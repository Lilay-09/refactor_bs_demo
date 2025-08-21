<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisbursementPackage extends Model
{
    // use HasFactory;
    protected $table = 'disbursement_packages';

    protected $fillable = [
        'package_id',
        'disbursement_id',
        'type',
        'payee_type',
        'is_deleted',
        'deleted_uid',
        'deleted_datetime',
        'deleted_reason'
    ];

    public function disbursement(){
        return $this->belongsTo(Disbursement::class,'disbursement_id','id');
    }
}
