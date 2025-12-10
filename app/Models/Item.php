<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'subcategory_id', 'name', 'type', 'rate', 'strength', 'manufacture_id', 'nomenclature', 'strength_unit_id', 'minimumlevel', 'unit_id', 'pack'];

    public function subcategory()
    {
        return $this->belongsTo(SubCategory::class, 'subcategory_id', 'id');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
    public function strengthunit()
    {
        return $this->belongsTo(StrengthUnit::class, 'strength_unit_id', 'id');
    }
    public function unit()
    {
        return $this->belongsTo(Uom::class, 'unit_id', 'id');
    }
    public function manufacture()
    {
        return $this->hasMany(ItemManufacture::class, 'item_id', 'id')->with('manufacturedropdown');
    }

    public function iteminventory()
    {
        $date = date('y-m-d');
        return $this->hasMany(ItemInventory::class, 'item_id', 'id')
            ->where('expiry_date', '>', $date)
            ->where('is_dummy', 0)
            ->groupby('item_id', 'batch_no', 'manufacture_id', 'expiry_date')
            ->select(DB::raw('SUM(quantity_in) - SUM(quantity_out) as item_available'), 'item_id', 'batch_no', 'manufacture_id', 'expiry_date')->with('manufacture');
    }
   public function purchaseOrderChild()
    {
        return $this->hasMany(PurchaseOrderChild::class, 'item_id');
    }

    public function purchaseOrders()
    {
        return $this->hasManyThrough(PurchaseOrder::class, PurchaseOrderChild::class, 'item_id', 'id', 'id', 'purchase_order_id');
    }
    public function itemAvaiableInventory()
    {
        $date = date('y-m-d');
        return $this->hasOne(ItemInventory::class, 'item_id', 'id')
            ->where('is_dummy', 0)->groupby('item_id')
            ->where('expiry_date', '>', $date)
            ->select(DB::raw('SUM(quantity_in) - SUM(quantity_out) as item_available'), 'item_id');
        }
    public function itemAvaiableAndExpiredInventory()
    {
        $date = date('y-m-d');
        return $this->hasOne(ItemInventory::class, 'item_id', 'id')
            ->where('is_dummy', 0)->groupby('item_id')
            ->where('expiry_date', '>', $date)
            ->select(DB::raw('SUM(quantity_in) - SUM(quantity_out) as item_available'), 'item_id');
    }
    public static function calculateTotalStockQty($itemId)
    {
        $date = date('y-m-d');
        $data = ItemInventory::where('is_dummy', 0)
            ->where('item_id', $itemId)
            ->where('expiry_date', '>', $date)
            ->groupby('item_id')
            ->select(DB::raw('SUM(quantity_in) - SUM(quantity_out) as item_available'))
            ->first();

        if ($data) {
            return $data->item_available;
        }
        return 0;
    }
}
