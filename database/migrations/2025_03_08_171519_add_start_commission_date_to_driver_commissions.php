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
        Schema::table('driver_commissions', function (Blueprint $table) {
            //
            $table->dateTime('start_pickup_commission_date')->nullable();
            $table->dateTime('start_delivery_commission_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_commissions', function (Blueprint $table) {
            //
            $table->dropColumn([
                'start_delivery_commission_date',
                'start_pickup_commission_date'
            ]);
        });
    }
};
