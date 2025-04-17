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
        Schema::table('zones', function (Blueprint $table) {
            //
            $table->decimal('loc_lat',19,7)->default(0);
            $table->decimal('loc_lng',19,7)->default(0);
            $table->decimal('radius_m',10,2)->default(0);
            $table->text('pin_map')->nullable();
            $table->string('address')->nullable();
            $table->enum('radius_type',['flexible','fixed'])->default('flexible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            //
            $table->dropColumn([
                'loc_lat',
                'loc_lng',
                'radius_m',
                'pin_map',
                'address',
                'radius_type'
            ]);
        });
    }
};
