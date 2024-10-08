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
            $table->unsignedBigInteger('driver_id')->nullable()->change();
            $table->decimal('additional_fee',10,2)->nullable()->default(0);
            $table->decimal('driver_total',10,2)->default(0);
            $table->decimal('merchant_total',10,2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn(['additional_fee','driver_total','merchant_total']);
            $table->unsignedBigInteger('driver_id')->nullable()->change();
        });
    }
};
