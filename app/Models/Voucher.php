<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\VoucherTransactionInvoice;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['voucher_type_id', 'name', 'voucher_no', 'purchase_order_id', 'date', 'total_amount', 'cheque_no', 'cheque_date', 'is_approved', 'is_post_dated', 'is_auto', 'generated_at', 'return_invoice_id', 'return_po_id', 'created_by', 'deleted_at', 'updated_at'];

    public function voucherTransactions()
    {
        return $this->hasMany(VoucherTransaction::class)->with('coaAccount');
    }

    public function voucherType()
    {
        return $this->belongsTo(VoucherType::class, 'type', 'id');
    }

    /**
     * getting voucher no
     *
     * @param  string  $value
     * @return string
     */
    public function getVoucherNoAttribute($value)
    {
        $voucherType = VoucherType::find($this->type);
        if (strlen($value) == 1) {
            return $voucherType->name . '00' . $value;
        } elseif (strlen($value) == 2) {
            return $voucherType->name . '0' . $value;
        } else {
            return $voucherType->name . $value;
        }
    }

    // public function editedVouchers()
    // {
    //     return $this->hasMany(EditedVoucher::class);
    // }

    public function voucherInvoices()
    {
        return $this->belongsTo(VoucherTransactionInvoice::class);
    }
}
