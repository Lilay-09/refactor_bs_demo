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
        Schema::create('invoices', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('ref_code')->nullable();
            $table->decimal('tax')->default(0);
            $table->decimal('exchange_rate')->default(0);
            $table->unsignedBigInteger('customer_id');
            $table->date('issue_date')->default(now());
            $table->date('due_date')->default(now());
            $table->string('customer_phone')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('discount_percent',20)->default('%');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('remarks',250)->nullable();
            $table->string('currency')->default('$');
            $table->tinyInteger('status_id')->default(1);
            $table->unsignedBigInteger('stock_location_id')->nullable();
            $table->foreign('stock_location_id')->references('id')->on('stock_locations');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
