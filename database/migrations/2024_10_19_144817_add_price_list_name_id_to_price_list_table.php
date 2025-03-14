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
        Schema::table('price_list', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('price_list_name_id')->nullable();
            $table->foreign('price_list_name_id')->references('id')->on('price_list_names');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_list', function (Blueprint $table) {
            //
            // $table->dropForeign('price_list_name_id');
            $table->dropColumn([
                'price_list_name_id'
            ]);
        });
    }
};
