<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryChallan extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';
    protected $fillable = ['dcnumber', 'person_id', 'quotationdate', 'status', 'dcdate'];

    public function deliverychallanchild()
    {
        return $this->hasMany(DeliveryChallanChild::class, 'dcparentid', 'id')->with('item');
    }
}
