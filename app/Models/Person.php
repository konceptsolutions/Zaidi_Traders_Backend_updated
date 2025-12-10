<?php

namespace App\Models;

use App\Models\Invoice;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Person extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'name',
        'person_type',
        'father_name',
        'phone_no',
        'email',
        'cnic',
        'address',
        'isActive',
        'ntn',
        'gst',
        'dsl',
    ];

    public function personType()
    {
        return $this->hasOne(PersonType::class, 'id', 'person_type');
    }

    public function coaAccount()
    {
        return $this->hasMany(CoaAccount::class);
    }

    public function item2()
    {
        return $this->hasMany(ItemManufacture::class, 'manufacture_id', 'id')->with('invoiceChildCount');
    }

    public function invoice()
    {
        return $this->hasMany(Invoice::class, 'customer_id', 'id')->with('invoiceChild', 'store');
    }

    public function purchaseorder()
    {
        return $this->hasMany(PurchaseOrder::class, 'person_id', 'id')->with('purchaseorderchild', 'store');
    }

    public function salesRepInvoice()
    {
        return $this->hasMany(Invoice::class, 'sales_rep_id', 'id')->with('invoiceChild', 'store', 'customer');
    }
    public function itemManufacture()
    {
        return $this->hasMany(ItemManufacture::class, 'manufacture_id', 'id')->with('invoiceChild');
    }
    public function purchaseorderchild()
    {
        return $this->hasMany(PurchaseOrderChild::class,'purchase_order_id');
    }

}
