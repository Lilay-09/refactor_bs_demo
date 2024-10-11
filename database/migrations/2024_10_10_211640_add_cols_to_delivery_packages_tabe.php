<?php

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
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('driver_id');
            $table->string('zone_code',50)->nullable();
            $table->string('zone_name',50)->nullable();
            $table->dateTime('failed_datetime')->nullable();
            $table->dateTime('assign_driver_datetime')->nullable();
            $table->string('failure_notes',500)->nullable();
            $table->string('receiver_address',500)->nullable();
            $table->boolean('cod')->default(0);
            $table->decimal('additional_fee')->default(0);
            $table->string('product_type',35)->nullable();
            $table->decimal('price',10,2)->default(0);
            $table->decimal('dim_x',10,2)->default(0);
            $table->decimal('dim_y',10,2)->default(0);
            $table->decimal('dim_z',10,2)->default(0);
            $table->string('delivery_type',50)->default('normal');
            $table->decimal('actual_kg',10,2)->default(0);
            $table->decimal('billed_kg',10,2)->default(0);
            $table->decimal('extra_charge',10,2)->default(0);
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dropForeign([
                'driver_id'
            ]);

            $table->dropColumn([
                'driver_id',
                'zone_code',
                'zone_name',
                'cod',
                'additional_fee',
                'product_type',
                'dim_x',
                'price',
                'dim_z',
                'dim_y',
                'actual_kg',
                'billed_kg',
                'extra_charge',
                'delivery_type',
                'assign_driver_datetime',
                'failure_notes',
                'receiver_address',
                'failed_datetime'
            ]);
        });
    }
};
