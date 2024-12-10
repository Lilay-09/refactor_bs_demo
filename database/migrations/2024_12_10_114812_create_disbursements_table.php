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
        Schema::create('disbursements', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('payee_id');
            $table->string('payee_type',50)->nullable();
            $table->decimal('amount',15,2)->default(0);
            $table->decimal('payable_amount',15,2)->default(0);
            $table->decimal('delivery_fee',15,2)->default(0);
            $table->decimal('taxi_fee',15,2)->default(0);
            $table->string('currency_code',25)->default('USD');
            $table->decimal('cod_amount',15,2)->default(0);
            $table->unsignedInteger('package_count')->default(0);
            $table->unsignedInteger('delivered_package_count')->default(0);
            $table->unsignedInteger('pickup_package_count')->default(0);
            $table->boolean('approved')->default(false);
            $table->boolean('is_settled')->default(false);
            $table->string('remarks',500)->nullable();
            $table->string('breakdown_notes',500)->nullable();
            $table->unsignedBigInteger('approved_uid')->nullable();
            $table->unsignedBigInteger('settled_uid')->nullable();
            $table->decimal('exchange_rate')->default(0);
            $table->datetime('approved_datetime')->nullable();
            $table->datetime('settled_datetime')->nullable();
            $table->datetime('payment_datetime')->nullable();
            $table->foreign('approved_uid')->references('id')->on('users');
            $table->foreign('settled_uid')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
