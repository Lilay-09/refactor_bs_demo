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
        Schema::table('stock_adjustment_details', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->decimal('cost',10,2)->default(0);
            $table->decimal('retail_price')->default(0);
            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustment_details', function (Blueprint $table) {
            //
            $table->dropForeign(['variant_id']);

            // Drop columns
            $table->dropColumn(['variant_id', 'retail_price','cost']);
        });
    }
};
