<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ItemInventory extends Model
{
    use HasFactory;
    protected $table = 'item_inventory';
    protected $fillable = ['purchase_order_id', 'invoice_id', 'inventory_type_id', 'item_id', 'manufacture_id', 'expiry_date', 'batch_no', 'purchase_price', 'store_id', 'quantity_in', 'quantity_out', 'return_invoice_id', 'is_dummy'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('subcategory', 'unit', 'category', 'itemAvaiableInventory', 'strengthunit');
    }
    public function item2()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('subcategory', 'unit', 'category', 'itemAvaiableAndExpiredInventory');
    }

    public function manufacture()
    {
        return $this->belongsTo(Person::class, 'manufacture_id', 'id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class)->with('supplier');
    }
    public function returnPurchaseOrder()
    {
        return $this->belongsTo(ReturnPurchaseOrder::class, 'return_po_id', 'id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id')->with('storeType');
    }
    public function inventoryType()
    {
        return $this->belongsTo(InventoryType::class, 'inventory_type_id', 'id');
    }

    public function itemInventory()
    {
        return $this->hasOne(ItemInventory::class, 'item_id', 'id')->where('is_dummy', 0)->groupBy('item_id', 'store_id')
            ->select(DB::raw('SUM(quantity_in) - SUM(quantity_out) as quantity'), 'id', 'item_id', 'store_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->with('customer');
    }
    public function returnInvoice()
    {
        return $this->belongsTo(InvoiceReturn::class, 'return_invoice_id', 'id');
    }
    public function invoicechild()
    {
        return $this->belongsTo(InvoiceChild::class, 'invoice_id', 'id')->with('invoice', 'customer');
    }

    public function purchaseOrderchild()
    {
        return $this->belongsTo(PurchaseOrderChild::class, 'purchase_order_id', 'id')->with('PurchaseOrder', 'supplier');
    }

    public function returnPurchaseOrderchild()
    {
        return $this->belongsTo(ReturnPurchaseOrderChild::class, 'return_po_id');
    }

    public function returnInvoicechild()
    {
        return $this->belongsTo(InvoiceChildReturn::class, 'return_invoice_id');
    }

    public static function getStockQuantity($manufacture_id, $itemId, $expiry_date, $batch_no)
    {
        $stock = ItemInventory::select(DB::raw('SUM(quantity_in) - SUM(quantity_out) as quantity'))
            ->where('expiry_date', $expiry_date)
            ->where('item_id', $itemId)
            ->where('manufacture_id', $manufacture_id)
            ->where('batch_no', $batch_no)
            ->first();

        return $stock ? $stock->quantity : 0;
        //return  $stockQuantity = ItemInventory::getStockQuantity($storeId, $itemId);
    }

    public function customer()
    {
        return $this->belongsTo(Person::class);
    }
}
