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
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->boolean('delay_count')->default(0);
            $table->boolean('is_completed')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dropColumn(['delay_count','is_completed']);
        });
    }
};
