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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedInteger('qty');
            $table->decimal('total_price');
            $table->decimal('unit_price');
            $table->decimal('discount_amount')->default(0);
            $table->string('discount_type',30)->default('%');
            $table->unsignedInteger('received_qty')->default(0);
            $table->date('expires_at')->nullable();
            $table->string('remarks',250)->nullable();
            $table->foreign('purchase_id')->references('id')->on('purchase_orders');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
