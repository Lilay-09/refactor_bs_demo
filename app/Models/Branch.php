<?php

namespace App\Models;

use App\Enums\BranchType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    // use HasFactory;
    protected $table = 'branches';
    protected $hidden = [];
    protected $fillable = [
        'name_en',
        'name_km',
        'address_en',
        'address_km',
        'email',
        'staff_count',
        'description_en',
        'description_km',
        'emergency_phone',
        'bm_name_en',
        'bm_name_km',
        'bm_phone',
        'company_id',
        'is_deleted',
        'deleted_uid',
        'deleted_reason',
        'create_uid',
        'update_uid'
    ];

    public function getBranchTypeAttribute(): ?string
    {
        if (!isset($this->branch_type_id)) {
            return null;
        }

        $enum = BranchType::tryFrom($this->branch_type_id);
        return $enum?->label();
    }
}
