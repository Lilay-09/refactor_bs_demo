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
            $table->unsignedBigInteger('merchant_disbursement_id')->nullable();
        });

        Schema::table('disbursements', function (Blueprint $table) {
            //
            $table->integer('failed_with_fee_count')->default(0);
        });

        Schema::table('payments', function (Blueprint $table) {
            //
            $table->integer('failed_with_fee_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn('merchant_disbursement_id');
        });

        Schema::table('disbursements', function (Blueprint $table) {
            //
            $table->dropColumn('failed_with_fee_count');
        });

        Schema::table('payments', function (Blueprint $table) {
            //
            $table->dropColumn('failed_with_fee_count');
        });
    }
};
