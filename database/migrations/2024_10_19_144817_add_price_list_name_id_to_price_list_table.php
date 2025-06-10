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
            $table->decimal('taxi_fee')->default(0);
            $table->decimal('other_fee')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_list', function (Blueprint $table) {
            $table->dropColumn([
                'price_list_name_id',
                'taxi_fee',
                'other_fee'
            ]);
        });
    }
};
