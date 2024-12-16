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
            $table->timestampsTz();
            $table->unsignedBigInteger('create_uid')->nullable();
            $table->unsignedBigInteger('update_uid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_list_zones', function (Blueprint $table) {
            //
            $table->dropColumn(['created_at', 'updated_at','create_uid','update_uid']);
        });
    }
};
