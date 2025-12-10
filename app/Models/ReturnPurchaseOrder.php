<?php

namespace App\Models;

use App\Models\Person;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use App\Models\ReturnPurchaseOrderChild;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReturnPurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'return_po';
    protected $fillable = ['po_id', 'adv_tax', 'adv_tax_percentage', 'return_date', 'deleted_at'];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($ReturnPurchaseOrder) {
            $parentInvoice = $ReturnPurchaseOrder->PurchaseOrder;
            $lastInvoiceReturn = static::withTrashed()->where('po_id', $ReturnPurchaseOrder->po_id)->latest('ret_po_no')->first();
            $lastInvoiceReturnNumber = $lastInvoiceReturn ? intval(substr(strrchr($lastInvoiceReturn->ret_po_no, "-"), 1)) : 0;
            $newInvoiceReturnNumber = $lastInvoiceReturnNumber + 1;
            $ReturnPurchaseOrder->ret_po_no = $parentInvoice->po_no . '-' . $newInvoiceReturnNumber;
        });
    }
    public function purchaseorder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id', 'id')->with('supplier', 'purchaseorderchild');
    }

    public function returnPoChild()
    {
        return $this->belongsTo(ReturnPurchaseOrderChild::class,'ret_po_id', 'id');
    }

    public function pochild()
    {
        return $this->hasMany(ReturnPurchaseOrderChild::class, 'ret_purchase_order_id', 'id')->with('item', 'manufacturer');
    }

    public function supplier()
    {
        return $this->belongsTo(Person::class);
    }

}
