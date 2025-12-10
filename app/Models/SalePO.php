<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SalePO extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'sales_po';
    protected $fillable = ['person_id', 'sales_rep_id', 'po_no', 'store_id', 'remarks', 'is_received', 'is_inv_generated', 'is_approved', 'is_cancel', 'request_date', 'total', 'discount', 'tax', 'total_after_tax', 'tax_in_figure', 'total_after_discount', 'created_by'];
    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($SalePO) {
    //         $lastRec = static::withTrashed()->latest('po_no')->first();
    //         $lastRecNumber = $lastRec ? $lastRec->invoice_no : '1000';
    //         $newRecNumber = (int) $lastRecNumber + 1;
    //         $SalePO->po_no = (string) $newRecNumber;
    //     });
    // }
    public function purchaseorderchild()
    {
        return $this->hasMany(SalePoChild::class, 'purchase_order_id', 'id')->with('item');
    }

    public function purchasevoucher()
    {
        return $this->hasMany(Voucher::class, 'purchase_order_id', 'id')->with('voucherTransactions');
    }

    public function customer()
    {
        return $this->belongsTo(Person::class, 'person_id', 'id');
    }
    public function salesrep()
    {
        return $this->belongsTo(Person::class, 'sales_rep_id', 'id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }
}
