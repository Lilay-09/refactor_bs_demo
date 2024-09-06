<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivePo extends Model
{
    use HasFactory;

    protected $table = 'receive_po';

    protected $fillable = [
        'id',
        'receive_number',
        'receive_uid',
        'purchase_id',
        'receive_date',
        'create_uid',
        'update_uid',
        'branch_id',
        'company_id'
    ];
}
