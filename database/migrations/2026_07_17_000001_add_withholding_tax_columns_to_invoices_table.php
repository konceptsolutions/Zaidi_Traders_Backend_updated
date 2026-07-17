<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWithholdingTaxColumnsToInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('wht_type')->nullable()->default(1)->after('adv_tax_type');
            $table->double('wht_percentage')->nullable()->default(0)->after('total_after_adv_tax');
            $table->double('wht_amount')->nullable()->default(0)->after('wht_percentage');
            $table->double('total_after_wht')->nullable()->default(0)->after('wht_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['wht_type', 'wht_percentage', 'wht_amount', 'total_after_wht']);
        });
    }
}
