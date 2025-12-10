<?php

namespace App\Models;

use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceReturn extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'invoices_return';
    protected $fillable = ['invoice_id', 'adv_tax_percentage', 'adv_tax', 'date', 'return_date', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoiceReturn) {
            $parentInvoice = $invoiceReturn->invoice;
            $lastInvoiceReturn = static::withTrashed()->where('invoice_id', $invoiceReturn->invoice_id)->latest('ret_invoice_no')->first();
            $lastInvoiceReturnNumber = $lastInvoiceReturn ? intval(substr(strrchr($lastInvoiceReturn->ret_invoice_no, "-"), 1)) : 0;
            $newInvoiceReturnNumber = $lastInvoiceReturnNumber + 1;
            $invoiceReturn->ret_invoice_no = $parentInvoice->invoice_no . '-' . $newInvoiceReturnNumber;
        });
    }
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'id')->with('customer', 'salesrep', 'store', 'invoiceChild');
    }

    public function invoiceChild()
    {
        return $this->hasMany(InvoiceChildReturn::class, 'ret_invoice_id', 'id')->with('item', 'manufacturer');
    }

    public function customer()
    {
        return $this->belongsTo(Person::class , 'customer_id' , 'id');
    }
}
