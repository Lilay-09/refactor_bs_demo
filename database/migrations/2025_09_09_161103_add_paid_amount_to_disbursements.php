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
            $table->decimal('paid_amount_usd',17,5)->default(0);
            $table->decimal('paid_amount_khr',17,5)->default(0);
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
                'paid_amount_khr',
                'paid_amount_usd'
            ]);
        });
    }
};
