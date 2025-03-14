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
            $table->unsignedBigInteger('assign_uid')->default(1);
        });
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('assign_uid')->default(1);
        });
        Schema::table('orders', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('assign_uid')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn('assign_uid');
        });
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dropColumn('assign_uid');
        });
        Schema::table('orders', function (Blueprint $table) {
            //
            $table->dropColumn('assign_uid');
        });
    }
};
