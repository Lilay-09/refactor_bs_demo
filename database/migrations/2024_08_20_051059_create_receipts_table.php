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
        Schema::create('receipts', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('ref_code')->nullable();
            $table->decimal('tax')->default(0);
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->decimal('exchange_rate')->default(0);
            $table->date('receipt_date')->default(now());
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_phone')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('discount_percent',20)->default('%');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('currency_rate')->default(0);
            $table->boolean('general')->default(1)->comment('if walk-in customer general = 1');
            $table->string('remarks',250)->nullable();
            $table->string('currency')->default('$');
            $table->unsignedBigInteger('stock_location_id')->nullable();
            $table->foreign('stock_location_id')->references('id')->on('stock_locations');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
