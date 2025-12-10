<?php

namespace App\Models;

use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'purchase_orders';
    protected $fillable = ['person_id', 'name', 'po_no', 'manufacture_id', 'store_id', 'remarks', 'is_received', 'is_completed', 'is_approved', 'is_cancel', 'receive_date', 'request_date', 'total', 'discount', 'tax', 'adv_tax', 'adv_tax_percentage', 'total_after_tax', 'tax_in_figure', 'total_after_discount', 'created_by', 'po_type', 'is_mailsent'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($PurchaseOrder) {
            $lastInvoice = static::withTrashed()->latest('po_no')->first();
            $lastInvoiceNumber = $lastInvoice ? $lastInvoice->po_no : '1000000';
            $newInvoiceNumber = (int) $lastInvoiceNumber + 1;
            $PurchaseOrder->po_no = (string) $newInvoiceNumber;
        });
    }
    public function purchaseorderchild()
    {
        return $this->hasMany(PurchaseOrderChild::class, 'purchase_order_id', 'id')->with('item' ,'supplier');
    }

    public function purchasevoucher()
    {
        return $this->hasMany(Voucher::class, 'purchase_order_id', 'id')->with('voucherTransactions');
    }

    public function supplier()
    {
        return $this->belongsTo(Person::class, 'person_id', 'id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }
   

    public function items()
    {
        return $this->hasManyThrough(Item::class, PurchaseOrderChild::class, 'purchase_order_id', 'id', 'id', 'item_id');
    }
     public function itemInventory()
    {
        return $this->hasManyThrough(ItemInventory::class,  'purchase_order_id');
    }
    
}
