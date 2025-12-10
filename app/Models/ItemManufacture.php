<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemManufacture extends Model
{
    use HasFactory;
    protected $table = 'item_manufacture';
    protected $fillable = [
        'item_id',
        'manufacture_id',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class, 'manufacture_id', 'id');
    }
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('unit', 'StrengthUnit');
    }

    public function manufacture()
    {
        return $this->belongsTo(Person::class, 'manufacture_id', 'id');
    }

    public function manufacturedropdown()
    {
        return $this->belongsTo(Person::class, 'manufacture_id', 'id')->select('id', 'id as value', 'name as label');
    }

    public function invoiceChildCount()
    {
        return $this->hasMany(InvoiceChild::class,  'item_id', 'item_id')
            ->select('item_id')
            ->groupBy('item_id')
            ->selectRaw('sum(quantity) as quantity_sum')
            ->selectRaw('sum(rate *quantity) as rate_sum')
            ->selectRaw('sum(amount) as amount_sum')
            ->with('invoiceCount', 'item');
    }

    public function invoiceChild()
    {
        return $this->hasMany(InvoiceChild::class,  'item_id', 'item_id')
            ->with('invoice', 'item');
    }
}
