<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';
    protected $fillable = [
        'id',
        'vendor_id',
        'name',
        'po_code',
        'issue_date',
        'tax',
        'discount_amount',
        'discount_type',
        'due_amount',
        'total_amount',
        'paid_amount',
        'approve_date',
        'approve_uid',
        'remarks',
        'expect_arrival_date',
        'cancel_date',
        'cancel_remarks',
        'cancel_date',
        'cancel_remarks',
        'status_id',
        'create_uid',
        'update_uid',
        'company_id',
        'branch_id'
    ];

    public function status(){
        return $this->belongsTo(PurchaseStatuses::class, 'status_id', 'id');
    }

    public function orderItems(){
        return $this->hasMany(PurchaseOrderItem::class,'purchase_id','id')->with(['variant']);
    }
}
