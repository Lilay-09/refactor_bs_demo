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
        Schema::create('stock_adjustment_details', function (Blueprint $table) {
            $this->AddBaseFields($table,true);
            $table->unsignedBigInteger('stock_adjustment_id');
            $table->string('item_ref');
            $table->unsignedInteger('qty');
            $table->string('reason',length: 250)->nullable();
            $table->foreign('stock_adjustment_id')->references('id')->on('stock_adjustments')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_uid')->nullable();
            $table->dateTime('approved_date')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();

            $table->foreign('warehouse_id')->references('id')->on('stock_locations');
            $table->foreign('approved_uid')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_details');
    }
};
