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
        Schema::table('user_zones', function (Blueprint $table) {
            //
            $table->string('user_type')->nullable();
            $table->boolean('is_primary')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_zones', function (Blueprint $table) {
            //
            $table->dropColumn('user_type');
            $table->dropColumn('is_primary');
        });
    }
};
