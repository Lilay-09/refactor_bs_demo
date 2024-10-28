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
        Schema::create('payments', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('payer_id');
            $table->string('payer_type',50);
            $table->decimal('amount',15,2)->default(0);
            $table->decimal('payable_amount',15,2)->default(0);
            $table->decimal('delivery_fee',15,2)->default(0);
            $table->decimal('taxi_fee',15,2)->default(0);
            $table->string('currency_code',25)->default('USD');
            $table->decimal('cod_amount',15,2)->default(0);
            $table->unsignedInteger('package_count')->default(0);
            $table->unsignedInteger('delivered_package_count')->default(0);
            $table->boolean('approved')->default(false);
            $table->string('remarks',500)->nullable();
            $table->string('breakdown_notes',500)->nullable();
            $table->unsignedBigInteger('approve_uid')->nullable();
            $table->unsignedBigInteger('receiver_uid');
            $table->decimal('exchange_rate')->default(0);
            $table->datetime('payment_datetime')->nullable();
            $table->foreign('approve_uid')->references('id')->on('users');
            $table->foreign('receiver_uid')->references('id')->on('users');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
