<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;
    protected $fillable = ['store_type_id', 'name', 'address'];

    public function storeType()
    {
        return $this->belongsTo(StoreType::class, 'store_type_id', 'id');
    }
}
