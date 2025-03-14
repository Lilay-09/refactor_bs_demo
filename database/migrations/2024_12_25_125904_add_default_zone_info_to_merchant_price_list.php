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
        Schema::table('merchant_price_list', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('zone_code',35)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_price_list', function (Blueprint $table) {
            //
            $table->dropColumn(['zone_id','zone_code']);
        });
    }
};
