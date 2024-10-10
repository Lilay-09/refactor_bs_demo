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
        Schema::create('deliveries', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('fleet_tracking_number',35)->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('status_id');
            $table->dateTime('depart_datetime')->nullable();
            $table->string('remarks',500)->nullable();
            $table->unsignedInteger('package_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedBigInteger('warehouse_id');
            $table->string('vehicle_type',50)->nullable();
            $table->boolean('is_completed')->default(0);
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('tracking_statuses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
