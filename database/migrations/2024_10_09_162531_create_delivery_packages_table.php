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
        Schema::create('delivery_packages', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('status_id');
            $table->dateTime('drop_datetime')->nullable();
            $table->string('driver_notes',500)->nullable();
            $table->string('notes',500)->nullable();

            $table->foreign('delivery_id')->references('id')->on('deliveries');
            $table->foreign('status_id')->references('id')->on('tracking_statuses');
            $table->foreign('package_id')->references('id')->on('packages');
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
