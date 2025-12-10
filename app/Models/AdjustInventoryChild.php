<?php

namespace App\Models;

use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdjustInventoryChild extends Model
{
    use HasFactory, SoftDeletes;

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('category', 'subcategory');
    }

    public function manufacture()
    {
        return $this->belongsTo(Person::class, 'manufacture_id', 'id');
    }

}
