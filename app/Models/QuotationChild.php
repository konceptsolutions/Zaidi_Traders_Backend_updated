<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationChild extends Model
{
    use HasFactory;

    protected $table = 'quotation_children';
    protected $fillable = ['parent_id', 'item_id', 'quantity', 'pack', 'quoted_rate', 'manufacture_id', 'retail_price', 'trade_price', 'quoted_price', 'total', 'remarks'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('category', 'subcategory', 'unit');
    }

    public function manufacture()
    {
        return $this->belongsTo(Person::class, 'manufacture_id', 'id');
    }
}
