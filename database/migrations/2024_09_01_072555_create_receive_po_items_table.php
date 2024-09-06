<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receive_po_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedBigInteger('receive_po_id');
            $table->unsignedInteger('received_qty');
            $table->string('batch_number',50)->nullable();
            $table->timestamps();
            $table->foreign('receive_po_id')->references('id')->on('receive_po');
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receive_po_items');
    }
};
