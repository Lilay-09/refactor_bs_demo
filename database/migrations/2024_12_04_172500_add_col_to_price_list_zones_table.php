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
        Schema::table('price_list_zones', function (Blueprint $table) {
            //
            $table->decimal('base_fee')->default(0);
            $table->decimal('additional_fee')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_list_zones', function (Blueprint $table) {
            //
            $table->dropColumn([
                'base_fee','additional_fee'
            ]);
        });
    }
};
