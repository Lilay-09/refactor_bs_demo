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
            $table->string('pickup_rate_type',25)->nullable();
            $table->string('delivery_rate_type',25)->nullable();
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
                'pickup_rate_type',
                'delivery_rate_type'
            ]);
        });
    }
};
