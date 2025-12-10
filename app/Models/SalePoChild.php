<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalePoChild extends Model
{
    use HasFactory;
    protected $table = 'sales_po_children';
    protected $fillable = ['purchase_order_id', 'item_id', 'batch_no', 'quantity', 'pack', 'quoted_rate', 'rate', 'received_quantity', 'returned_quantity', 'purchase_price', 'total', 'remarks'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('category', 'subcategory', 'unit');
    }
}
