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
        //
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->decimal('cod_usd',15,2)->default(0)->change();
            $table->decimal('cod_khr',15,2)->default(0)->change();
            $table->decimal('driver_cod_usd',15,2)->default(0)->change();
            $table->decimal('driver_cod_khr',15,2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->decimal('cod_usd',15,2)->change();
            $table->decimal('cod_khr',15,2)->change();
            $table->decimal('driver_cod_usd',15,2)->change();
            $table->decimal('driver_cod_khr',15,2)->change();
        });
    }
};
