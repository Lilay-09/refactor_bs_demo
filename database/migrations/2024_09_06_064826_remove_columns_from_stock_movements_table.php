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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn([
                'adjustment_qty',
                'transfer_in_qty',
                'transfer_out_qty',
                'sold_qty',
                'receive_qty',
            ]);
            $table->integer('qty')->default(0);
            $table->decimal('retail_price',10,2)->default(0);
            $table->decimal('wholesale_price',10,2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('adjustment_qty')->default(0);
            $table->unsignedInteger('transfer_in_qty')->default(0);
            $table->integer('transfer_out_qty')->default(0);
            $table->integer('sold_qty')->default(0);
            $table->unsignedInteger('receive_qty')->default(0);

            $table->dropColumn('qty');
            $table->dropColumn('retail_price');
            $table->dropColumn('wholesale_price');
        });
    }
};
