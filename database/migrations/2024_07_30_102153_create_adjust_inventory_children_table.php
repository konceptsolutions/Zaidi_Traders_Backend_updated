<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdjustInventoryChildrenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('adjust_inventory_children', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adjust_inventory_id');
            $table->unsignedBigInteger('item_id');
            $table->double('quantity_in')->default(0);
            $table->double('quantity_out')->default(0);
            $table->double('purchase_price')->default(0);
            $table->integer('manufacture_id')->nullable();
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('packsize')->nullable();
            $table->double('total')->default(0);
            $table->foreign('adjust_inventory_id')
            ->references('id')->on('adjust_inventories')
            ->onDelete('cascade');
            $table->foreign('item_id')
            ->references('id')->on('items')
            ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('adjust_inventory_children');
    }
}
