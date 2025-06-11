<?php

use App\Enums\LocationType;
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
        Schema::create('delivery_packages', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('package_id');
            $table->string('qr_code',35)->nullable();
            $table->string('product_type',35)->nullable();
            $table->decimal('price',10,2)->default(0);
            $table->decimal('dim_x',10,2)->default(0);
            $table->decimal('dim_y',10,2)->default(0);
            $table->decimal('dim_z',10,2)->default(0);
            $table->unsignedBigInteger('status_id');
            $table->string('failure_notes',500)->nullable();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('photo_id')->nullable();
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
            $table->decimal('actual_kg',10,2)->default(0);
            $table->decimal('billed_kg',10,2)->default(0);
            $table->dateTime('delivered_datetime')->nullable();
            $table->dateTime('assign_driver_datetime')->nullable();
            $table->dateTime('arrive_warehouse_datetime')->nullable();
            $table->dateTime('completed_datetime')->nullable();
            $table->string('pickup_notes',500)->nullable();
            $table->dateTime('pickup_datetime')->nullable();
            $table->dateTime('failed_datetime')->nullable();
            $table->decimal('extra_charge',10,2)->default(0);
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->decimal('additional_fee',15,2)->nullable()->default(0);
            $table->decimal('driver_total',15,2)->default(0);
            $table->decimal('merchant_total',15,2)->default(0);

            $table->text('tracking_notes')->nullable();
            $table->string('return_notes',500)->nullable();
            // $table->integer('cod_changed')->default(0);
            $table->decimal('taxi_fee',10,2)->default(0);

            $table->string('remarks',500)->nullable();

            $table->boolean('is_contact')->default(0);
            $table->string('contact_reason',500)->nullable();
            $table->dateTime('contact_datetime')->nullable();
            $table->string('driver_notes',500)->nullable();
            $table->integer('priority_level')->default(0);
            $table->datetime('returned_datetime')->nullable();

            $table->text('kick_notes')->nullable();
            $table->unsignedBigInteger('kick_uid')->nullable();
            $table->string('kick_reason',250)->nullable();

            $table->string('delivery_remarks',350)->nullable();

            $table->unsignedBigInteger('returned_uid')->nullable();

            $table->unsignedBigInteger('assign_uid')->nullable();

            $table->unsignedBigInteger('last_submit_uid')->nullable();
            $table->string('last_remark_user',100)->nullable();

            $table->integer('driver_display_order')->default(0);

            $table->string('main_zone_name',50)->nullable();
            $table->string('main_zone_code')->nullable();
            $table->decimal('receiver_lat',19,7)->default(0);
            $table->decimal('receiver_lng',19,7)->default(0);
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->decimal('cod_usd',15,2);
            $table->decimal('cod_khr',15,2);
            $table->decimal('driver_cod_usd',15,2);
            $table->decimal('driver_cod_khr',15,2);

            $table->foreign('status_id')->references('id')->on('tracking_statuses')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('merchant_id')->references('id')->on('users')->onDelete('cascade');
            $table->text('notes')->nullable();

            $table->foreign('delivery_id')->references('id')->on('deliveries')->onDelete('cascade');

            $table->boolean('delay_count')->default(0);
            $table->boolean('is_completed')->default(0);

            $table->boolean('has_swap')->default(false);

            $table->string('location_type',50)->default(LocationType::WAREHOUSE);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_packages');
    }
};
