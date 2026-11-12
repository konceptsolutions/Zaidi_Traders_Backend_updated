<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoucherTransactionInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['voucher_id', 'invoice_id', 'amount'];

    /**
     * Amount of other-voucher receipts already applied to one invoice.
     * Uses the share stored on the link. Older single-invoice rows fall back
     * to the voucher total when that share was not stored.
     */
    public static function paidAmountForInvoice($invoiceId): float
    {
        return (float) DB::table('voucher_transaction_invoices')
            ->join('vouchers', 'voucher_transaction_invoices.voucher_id', '=', 'vouchers.id')
            ->where('voucher_transaction_invoices.invoice_id', $invoiceId)
            ->whereNull('voucher_transaction_invoices.deleted_at')
            ->whereNull('vouchers.deleted_at')
            ->sum(DB::raw('COALESCE(voucher_transaction_invoices.amount, vouchers.total_amount)'));
    }
}
