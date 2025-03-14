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
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('driver_payment_id')->nullable();
            $table->foreign('driver_payment_id')->references('id')->on('payments');
            $table->unsignedBigInteger('merchant_payment_id')->nullable();
            $table->foreign('merchant_payment_id')->references('id')->on('payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //'
            $table->dropColumn(['driver_payment_id','merchant_payment_id']);
        });
    }
};
