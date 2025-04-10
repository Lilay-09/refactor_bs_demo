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
            $table->string('trx_code',50)->nullable();
            $table->decimal('paid_amount',15,2)->default(0);
        });

        Schema::table('payments', function (Blueprint $table) {
            //
            $table->string('trx_code',50)->nullable();
            $table->decimal('paid_amount',15,2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            //
            $table->dropColumn(['trx_code','paid_amount']);
        });
        Schema::table('payments', function (Blueprint $table) {
            //
            $table->dropColumn(['trx_code','paid_amount']);
        });
    }
};
