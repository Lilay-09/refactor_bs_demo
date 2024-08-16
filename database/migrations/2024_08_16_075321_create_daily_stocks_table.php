<?php

use App\Traits\BaseMigrationField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use BaseMigrationField;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_stocks', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedInteger('stock_location_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedInteger('begin_qty')->nullable();
            $table->unsignedInteger('ending_qty')->nullable();
            $table->unsignedInteger('adjustment_qty')->nullable();
            $table->unsignedInteger('transfer_in_qty')->nullable();
            $table->unsignedInteger('transfer_out_qty')->nullable();
            $table->unsignedInteger('sold_qty')->nullable();
            $table->unsignedInteger('purchase_qty')->nullable();
            //*
            $table->foreign('stock_location_id')->references('id')->on('stock_locations');
            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_stocks');
    }
};
