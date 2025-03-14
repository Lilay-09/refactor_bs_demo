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
            $table->string('delivery_remarks',350)->nullable();
        });
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->string('delivery_remarks',350)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn('delivery_remarks');
        });
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dropColumn('delivery_remarks');
        });
    }
};
