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
        Schema::create('purchase_order_expenses', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders')
                  ->onDelete('cascade');
            $table->enum('expense_type', ['Goods', 'Shipping', 'Handling', 'Tax', 'Discount', 'Other'])
            ->default('Goods');
            $table->unsignedBigInteger('pmt_status_id')->default(1);
            $table->decimal('amount', 15, 2);
            $table->date('expense_date')->default(now());
            $table->string('description',250)->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_expenses');
    }
};
