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
        Schema::create('po_payment_slips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->decimal('amount')->default(0);
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('photo_file_name',200);
            $table->string('directory')->default('purchase_payment_slip');
            $table->foreign('purchase_id')->references('id')->on('purchase_orders');
            $table->foreign('payment_id')->references('id')->on('po_payments');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_payment_slips');
    }
};
