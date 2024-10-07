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
        Schema::create('packages', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('qr_code',35)->nullable();
            $table->string('product_type',35)->nullable();
            $table->decimal('price',10,2)->default(0);
            $table->decimal('dim_x',10,2)->default(0);
            $table->decimal('dim_y',10,2)->default(0);
            $table->decimal('dim_z',10,2)->default(0);
            $table->unsignedBigInteger('status_id');
            $table->string('failure_notes',500)->nullable();
            $table->unsignedBigInteger('order_id');
            $table->string('payer',50);
            $table->boolean(column: 'cod')->default(0);
            $table->decimal('delivery_fee',10,2)->default(0);
            $table->string('receiver_address')->nullable();
            $table->string('zone_code',50)->nullable();
            $table->string('zone_name',50)->nullable();
            $table->string('receiver_phone',25);
            $table->string('receiver_name',50)->nullable();
            $table->string('delivery_type',50);
            $table->decimal('actual_kg',10,2)->default(0);
            $table->decimal('billed_kg',10,2)->default(0);
            $table->dateTime('delivery_datetime')->nullable();
            $table->dateTime('arrive_warehouse_datetime')->nullable();
            $table->unsignedBigInteger('driver_id');

            $table->foreign('status_id')->references('id')->on('tracking_statuses')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
