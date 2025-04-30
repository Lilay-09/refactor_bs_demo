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
        Schema::table('disbursements', function (Blueprint $table) {
            //
            $table->decimal('fast_delivery_rate')->default(0);
            $table->decimal('fast_pickup_rate')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            //
            $table->dropColumn([
                'fast_delivery_rate',
                'fast_pickup_rate'
            ]);
        });
    }
};
