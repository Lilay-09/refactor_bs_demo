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
            $table->dateTime('deleted_datetime')->nullable();
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->boolean('is_deleted')->default(false);
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
                'deleted_datetime',
                'deleted_uid',
                'is_deleted'
            ]);
        });
    }
};
