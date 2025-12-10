<?php

namespace App\Models;

use App\Models\ReturnPurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReturnPurchaseOrderChild extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'return_po_children';
    protected $fillable = ['ret_purchase_order_id', 'item_id', 'batch_no', 'manufacturer_id', 'expiry_date', 'quantity', 'pack', 'quoted_rate', 'rate', 'received_quantity', 'returned_quantity', 'purchase_price', 'total', 'remarks'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('category', 'subcategory', 'unit');
    }

    public function returnPurchaseOrder()
    {
        return $this->belongsTo(ReturnPurchaseOrder::class, 'ret_purchase_order_id', 'id')->with('supplier');
    }


    public function PoNo()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id')
            ->where('is_received', '=', 1)->select('id', 'po_no', 'request_date', 'is_received', 'person_id')->with('supplier');
    }

    public function manufacturer()
    {
        return $this->belongsTo(Person::class, 'manufacturer_id', 'id');
    }
}
