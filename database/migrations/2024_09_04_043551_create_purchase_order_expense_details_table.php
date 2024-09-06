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
        Schema::create('purchase_order_expense_details', function (Blueprint $table) {
            $table->id();
            $table->enum('payment_method', ['Cash', 'Bank']);
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('purchase_order_expense_id')->nullable();
            $table->string('bank_number',30)->nullable();
            $table->decimal('amount',10,2);
            $table->string('photo_file_name',200)->nullable();

            $table->foreign('bank_id')->references('id')->on('banks');
            $table->foreign('purchase_order_expense_id')->references('id')->on('purchase_order_expenses');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_expense_details');
    }
};
