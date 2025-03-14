<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('merchant_price_list', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['price_list_id']);

            // Remove the old price_list_id column
            $table->dropColumn('price_list_id');

            // Add the new price_list_id column
            $table->unsignedBigInteger('price_list_id')->nullable();

            // Add the new foreign key constraint
            $table->foreign('price_list_id')->references('id')->on('price_list_names')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('merchant_price_list', function (Blueprint $table) {
            // Drop the new foreign key constraint
        $table->dropForeign(['price_list_id']);

        // Remove the new price_list_id column
        $table->dropColumn('price_list_id');

        // Add the old price_list_id column as nullable
        $table->unsignedBigInteger('price_list_id')->nullable();

        // Restore the old foreign key constraint
        $table->foreign('price_list_id')->references('id')->on('price_list')->onDelete('cascade');
        });
    }
};
