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
        Schema::create('orders', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('vehicle_type',50);
            $table->string('delivery_type',50)->default('normal');
            $table->string('product_type',50)->nullable();
            $table->unsignedInteger('qty')->default(0);
            $table->string('pickup_address',500)->nullable();
            $table->text('pickup_address_google_map')->nullable();
            $table->text('tracking_notes')->nullable();
            $table->dateTime('pickup_datetime')->nullable();
            $table->string('code',50)->nullable();
            $table->string('tracking_number',50)->nullable();
            $table->string('pickup_notes',500)->nullable();
            $table->dateTime('order_datetime')->nullable();
            $table->boolean('is_completed')->default(0);
            $table->string('cancel_notes',500)->nullable();
            $table->decimal('loc_lat',9,6)->nullable();
            $table->decimal('loc_lng',9,6)->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->string('booking_channel',35)->nullable();

            $table->foreign('merchant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('tracking_statuses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
