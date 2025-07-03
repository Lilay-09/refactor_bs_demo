<?php

use App\Enums\PackageLogType;
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
        Schema::create('package_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('status_id');
            $table->unsignedBigInteger('order_id');
            $table->string('product_type',35)->nullable();
            $table->decimal('price',10,2)->default(0);
            $table->string('payer',50);
            $table->boolean( 'cod')->default(0);
            $table->decimal('delivery_fee',10,2)->default(0);
            $table->string('receiver_address',500)->nullable();
            $table->boolean('outstanding')->default(1);
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('zone_code',50)->nullable();
            $table->string('zone_name',50)->nullable();
            $table->string('receiver_phone',25);
            $table->string('receiver_name',50)->nullable();
            $table->string('delivery_type',50)->default('normal');
            $table->dateTime('delivered_datetime')->nullable();
            $table->dateTime('assign_driver_datetime')->nullable();
            $table->dateTime('arrive_warehouse_datetime')->nullable();
            $table->string('pickup_notes',500)->nullable();
            $table->dateTime('pickup_datetime')->nullable();
            $table->dateTime('failed_datetime')->nullable();
            $table->decimal('extra_charge',10,2)->default(0);
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->decimal('additional_fee',15,2)->nullable()->default(0);
            $table->decimal('driver_total',15,2)->default(0);
            $table->decimal('merchant_total',15,2)->default(0);
            $table->text('tracking_notes')->nullable();
            $table->string('return_notes',500)->nullable();
            $table->decimal('taxi_fee',10,2)->default(0);

            $table->string('remarks',500)->nullable();

            $table->string('main_zone_name',50)->nullable();
            $table->string('main_zone_code')->nullable();
            $table->decimal('receiver_lat',19,7)->default(0);
            $table->decimal('receiver_lng',19,7)->default(0);

            $table->decimal('cod_usd',15,2);
            $table->decimal('cod_khr',15,2);
            $table->decimal('driver_cod_usd',15,2);
            $table->decimal('driver_cod_khr',15,2);

            $table->foreign('status_id')->references('id')->on('tracking_statuses')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('action_uid')->nullable();

            $table->string('log_type',50)->default(PackageLogType::TRANSFER);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_logs');
    }
};
