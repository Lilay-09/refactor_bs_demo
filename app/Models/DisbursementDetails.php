<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisbursementDetails extends Model
{
    use HasFactory;
    protected $table = 'disbursement_details';
    protected $fillable = [
        'id',
        'method',
        'disbursement_id',
        'amount',
        'currency_code',
        'original_amount',
    ];
}
