<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAmountToVoucherTransactionInvoicesTable extends Migration
{
    /**
     * Store how much of a receipt applies to each invoice.
     * A receipt can cover more than one invoice, so the voucher total
     * must not be counted in full against every linked invoice.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('voucher_transaction_invoices', function (Blueprint $table) {
            $table->decimal('amount', 15, 4)->nullable()->after('invoice_id');
        });

        DB::statement('
            UPDATE voucher_transaction_invoices vti
            INNER JOIN vouchers v ON v.id = vti.voucher_id
            INNER JOIN (
                SELECT voucher_id
                FROM voucher_transaction_invoices
                WHERE deleted_at IS NULL
                GROUP BY voucher_id
                HAVING COUNT(*) = 1
            ) single_link ON single_link.voucher_id = vti.voucher_id
            SET vti.amount = v.total_amount
            WHERE vti.deleted_at IS NULL
        ');

        $multiVoucherIds = DB::table('voucher_transaction_invoices')
            ->whereNull('deleted_at')
            ->groupBy('voucher_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('voucher_id');

        foreach ($multiVoucherIds as $voucherId) {
            $voucher = DB::table('vouchers')->where('id', $voucherId)->first();
            if (!$voucher) {
                continue;
            }

            $links = DB::table('voucher_transaction_invoices')
                ->where('voucher_id', $voucherId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            $remaining = (float) $voucher->total_amount;
            $lastLinkId = $links->last()->id ?? null;

            foreach ($links as $link) {
                $invoice = DB::table('invoices')->where('id', $link->invoice_id)->first();
                $payable = 0.0;
                if ($invoice) {
                    if ((int) ($invoice->wht_type ?? 1) === 2 && (float) ($invoice->total_after_wht ?? 0) > 0) {
                        $payable = (float) $invoice->total_after_wht;
                    } elseif ((float) ($invoice->total_after_adv_tax ?? 0) > 0) {
                        $payable = (float) $invoice->total_after_adv_tax;
                    } else {
                        $payable = (float) ($invoice->total_after_gst ?? 0);
                    }
                }

                $already = (float) ($invoice->total_received ?? 0);
                $due = max(0.0, $payable - $already);

                if ((int) $link->id === (int) $lastLinkId) {
                    $applied = $remaining;
                } else {
                    $applied = min($remaining, $due);
                }
                $remaining -= $applied;

                DB::table('voucher_transaction_invoices')
                    ->where('id', $link->id)
                    ->update(['amount' => $applied]);
            }
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('voucher_transaction_invoices', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
}
