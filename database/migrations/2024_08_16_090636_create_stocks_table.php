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
        Schema::create('stocks', function (Blueprint $table) {
            $table->string('sku', 50);
            $table->string('batch_number', 50)->nullable();
            $table->unsignedBigInteger('stock_location_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedInteger('qty');
            $table->decimal('cost')->nullable();
            $table->decimal('wholesale_price')->nullable();
            $table->decimal('retail_price')->nullable();
            $table->date('expiration_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'pending', 'reserved'])->default('active');
            $table->timestamp("created_at")->useCurrent();
            $table->timestamp("updated_at")->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('create_uid');
            $table->unsignedBigInteger('update_uid');
            $table->unsignedBigInteger("branch_id");
            $table->unsignedBigInteger('company_id');

            /**
             * relationship
            */
            $table->foreign('variant_id')->references('id')->on('product_variants');
            $table->foreign('stock_location_id')->references('id')->on('stock_locations');
            $table->foreign('create_uid')->references('id')->on('users');
            $table->foreign('update_uid')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('company_id')->references('id')->on('companies');

            $table->primary('sku');
            $table->unique(['sku', 'batch_number']);
            $table->index('stock_location_id');
            $table->index('variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
