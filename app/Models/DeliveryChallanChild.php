<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryChallanChild extends Model
{
    use HasFactory;

    protected $table = 'delivery_challan_children';
    protected $fillable = ['dcparentid', 'subcategory_id', 'item_id', 'pack', 'batch_no', 'expiry_date', 'quantity', 'dcdate'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('subcategory', 'unit');
    }
}
