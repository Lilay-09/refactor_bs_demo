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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('variant_id');
            $table->integer('qty');
            $table->decimal('unit_price');
            $table->decimal('net_amount');
            $table->decimal('cost')->default(0);
            $table->decimal('tax')->default(0);
            $table->decimal('discount_amount')->default(0);
            $table->string('discount_type')->default('%');
            $table->string('description',250)->nullable();
            $table->timestamps();
            //
            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
