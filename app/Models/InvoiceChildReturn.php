<?php

namespace App\Models;

use App\Models\Person;
use App\Models\InvoiceReturn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceChildReturn extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'invoice_children_return';
    protected $fillable = ['ret_invoice_id', 'item_id', 'quantity',  'rate', 'cost', 'sales_tax', 'amount', 'returned_quantity', 'discount', 'total_amount', 'item_discount_per', 'deleted_at'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('subcategory', 'unit');
    }

    public function manufacturer()
    {
        return $this->belongsTo(ItemInventory::class, 'id', 'return_invoice_id')->with('manufacture');
    }

    public function invoice()
    {
        return $this->belongsTo(InvoiceReturn::class, 'ret_invoice_id')->with('customer');
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
        return $this->belongsTo(Person::class , 'customer_id' , 'id');
    }
}
