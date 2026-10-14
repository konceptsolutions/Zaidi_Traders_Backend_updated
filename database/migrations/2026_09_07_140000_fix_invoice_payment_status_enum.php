<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixInvoicePaymentStatusEnum extends Migration
{
    /**
     * Align payment_status enum with app values (partial_paid, not "partial paid").
     *
     * @return void
     */
    public function up()
    {
        // 1) Allow both old and new values during transition
        DB::statement("ALTER TABLE `invoices` MODIFY `payment_status` ENUM('unpaid','paid','partial paid','partial_paid') NOT NULL DEFAULT 'unpaid'");

        // 2) Remap legacy spaced value
        DB::table('invoices')
            ->where('payment_status', 'partial paid')
            ->update(['payment_status' => 'partial_paid']);

        // 3) Drop the old enum value
        DB::statement("ALTER TABLE `invoices` MODIFY `payment_status` ENUM('unpaid','paid','partial_paid') NOT NULL DEFAULT 'unpaid'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `invoices` MODIFY `payment_status` ENUM('unpaid','paid','partial paid','partial_paid') NOT NULL DEFAULT 'unpaid'");

        DB::table('invoices')
            ->where('payment_status', 'partial_paid')
            ->update(['payment_status' => 'partial paid']);

        DB::statement("ALTER TABLE `invoices` MODIFY `payment_status` ENUM('unpaid','paid','partial paid') NOT NULL DEFAULT 'unpaid'");
    }
}
