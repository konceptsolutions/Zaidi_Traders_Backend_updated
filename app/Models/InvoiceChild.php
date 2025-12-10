<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceChild extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['invoice_id', 'item_id', 'quantity',  'rate', 'cost', 'sales_tax', 'amount', 'returned_quantity', 'discount', 'total_amount', 'item_discount_per', 'deleted_at'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('subcategory', 'unit', 'strengthunit');
    }

    public function manufacturer()
    {
        return $this->belongsTo(ItemInventory::class, 'id', 'invoice_id')->with('manufacture');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->with('customer');
    }

    public function invoiceNo()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id')
            ->select('id', 'invoice_no', 'date', 'walk_in_customer_name', 'customer_id')->with('customer');
    }

    public function invoiceCount()
    {
        return $this->belongsTo(Invoice::class,  'id', 'invoice_id');
    }

    public function customer()
    {
        return $this->belongsTo(Person::class, 'customer_id', 'id');
    }
}
