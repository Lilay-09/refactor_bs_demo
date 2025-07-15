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
        Schema::table('order_images', function (Blueprint $table) {
            //
            $table->string('user_type')->default('customer')->after('order_id')->comment('Type of user who uploaded the image, e.g., customer, driver, etc.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_images', function (Blueprint $table) {
            //
            $table->dropColumn('user_type');
        });
    }
};
