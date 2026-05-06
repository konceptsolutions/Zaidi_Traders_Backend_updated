<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SetInvoiceChildrenCostFromItemsAvgCost extends Migration
{
    /**
     * Run the migrations.
     * Set cost = items.avg_cost in invoice_children and invoice_children_return.
     *
     * @return void
     */
    public function up()
    {
        // invoice_children: set cost from items.avg_cost by item_id
        DB::statement('
            UPDATE invoice_children ic
            INNER JOIN items i ON i.id = ic.item_id
            SET ic.cost = COALESCE(i.avg_cost, 0)
        ');

        // invoice_children_return: set cost from items.avg_cost by item_id
        DB::statement('
            UPDATE invoice_children_return icr
            INNER JOIN items i ON i.id = icr.item_id
            SET icr.cost = COALESCE(i.avg_cost, 0)
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Cannot reliably reverse
    }
}
