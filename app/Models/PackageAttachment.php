<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageAttachment extends Model
{
    use HasFactory;
    protected $table = 'package_attachments';
    protected $primaryKey = null;

    public $incrementing = false;

    // public $timestamps = false;

    protected $fillable = [
        'file_name',
        'hidden',
        'file_dir',
        'submit_uid',
        'user_class',
        'file_type',
        'package_id',
        'updated_at',
        'created_at'
    ];
}
