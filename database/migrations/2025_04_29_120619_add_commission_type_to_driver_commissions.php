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
            $table->string('pickup_commission_type')->default('percentage');
            $table->string('delivery_commission_type')->default('percentage');
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
                'pickup_commission_type',
                'delivery_commission_type'
            ]);
        });
    }
};
