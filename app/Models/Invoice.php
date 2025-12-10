<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['customer_id', 'sales_rep_id', 'sale_type', 'tax_type', 'is_approved', 'invoice_no', 'walk_in_customer_name', 'walk_in_customer_phone', 'date', 'remarks', 'store_id', 'total_amount', 'amount_received', 'bank_amount_received', 'discount', 'total_after_discount', 'gst', 'gst_percentage', 'adv_tax_percentage', 'adv_tax', 'total_after_gst', 'quotation_id', 'po_id', 'account_id', 'is_dummy', 'bank_account_id', 'deleted_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            $lastInvoice = static::withTrashed()->latest('invoice_no')->first();
            $lastInvoiceNumber = $lastInvoice ? $lastInvoice->invoice_no : '20167667';
            $newInvoiceNumber = (int) $lastInvoiceNumber + 1;
            $invoice->invoice_no = (string) $newInvoiceNumber;
        });
    }
    public function customer()
    {
        return $this->belongsTo(Person::class, 'customer_id', 'id');
    }

    public function salesrep()
    {
        return $this->belongsTo(Person::class, 'sales_rep', 'id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id', 'id')->select('id', 'quotation_no', 'termcondition');
    }

    public function posale()
    {
        return $this->belongsTo(SalePO::class, 'po_id', 'id')->select('id', 'po_no', 'remarks');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id')->with('storeType');
    }
    public function invoiceChild()
    {
        return $this->hasMany(InvoiceChild::class, 'invoice_id', 'id')->with('item');
    }
    public function invoiceReturn()
    {
        return $this->hasMany(InvoiceReturn::class, 'invoice_id', 'id')->with('invoiceChild');
    }
    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'invoice_id', 'id');
    }
}
