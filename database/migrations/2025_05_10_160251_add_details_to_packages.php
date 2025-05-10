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
            $table->string('main_zone_name',50)->nullable();
            $table->string('main_zone_code')->nullable();
            $table->decimal('receiver_lat',19,7)->default(0);
            $table->decimal('receiver_lng',19,7)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn([
                'main_zone_name',
                'main_zone_code',
                'receiver_lat',
                'receiver_lng'
            ]);
        });
    }
};
