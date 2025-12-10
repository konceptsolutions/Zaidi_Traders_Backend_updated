<?php

namespace App\Models;

use App\Models\Item;
use App\Models\Voucher;
use App\Models\ItemInventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdjustInventory extends Model
{
    use HasFactory;

    use SoftDeletes;

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('subcategory', 'category', 'itemAvaiableInventory', 'strengthunit');
    }
    public function itemInventory()
    {
        return $this->hasMany(ItemInventory::class, 'adjust_inventory_id', 'id')->select('adjust_inventory_id', 'item_id', 'purchase_price', 'quantity_in', 'quantity_out')->with(['item' => function ($query) {
            $query->select('id', 'name', 'category_id', 'subcategory_id');
            $query->with(['category' => function ($categoryQuery) {
                $categoryQuery->select('id', 'name');
            }, 'subcategory' => function ($subcategoryQuery) {
                $subcategoryQuery->select('id', 'name');
            }]);
        }]);
    }

    public function voucher()
    {
        return $this->hasOne(Voucher::class, 'adjust_inventory_id');
    }


    public function adjustInventoryChild()
    {
        return $this->hasMany(AdjustInventoryChild::class, 'adjust_inventory_id', 'id')->with('item');
    }
}
