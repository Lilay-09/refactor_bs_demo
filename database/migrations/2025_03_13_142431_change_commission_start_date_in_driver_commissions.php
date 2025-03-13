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
            $table->renameColumn('start_pickup_commission_date','pickup_commission_start_date');
            $table->renameColumn('start_delivery_commission_date','delivery_commission_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_commissions', function (Blueprint $table) {
            $table->renameColumn('pickup_commission_start_date', 'start_pickup_commission_date');
            $table->renameColumn('delivery_commission_start_date', 'start_delivery_commission_date');
        });
    }
};
