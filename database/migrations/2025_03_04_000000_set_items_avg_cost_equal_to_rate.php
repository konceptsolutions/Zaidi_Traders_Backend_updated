<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SetItemsAvgCostEqualToRate extends Migration
{
    /**
     * Run the migrations.
     * Set avg_cost = rate for all rows in items table.
     *
     * @return void
     */
    public function up()
    {
        DB::table('items')->update(['avg_cost' => DB::raw('COALESCE(rate, 0)')]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Cannot reliably reverse; leave data as is or no-op
    }
}
