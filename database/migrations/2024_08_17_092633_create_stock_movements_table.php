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
        Schema::create('stock_movements', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('variant_id');
            $table->string('reference_no',100)->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            $table->decimal('cost')->default(0);
            $table->integer('adjustment_qty')->default(0);
            $table->unsignedInteger('transfer_in_qty')->default(0);
            $table->integer('transfer_out_qty')->default(0);
            $table->integer('sold_qty')->default(0);
            $table->unsignedInteger('receive_qty')->default(0);
            $table->string('description',250)->nullable();

            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
